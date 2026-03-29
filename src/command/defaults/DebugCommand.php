<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\ddui\DDUI;
use pocketmine\ddui\element\DropdownItem;
use pocketmine\ddui\element\options\CloseButtonOptions;
use pocketmine\ddui\element\options\DropdownOptions;
use pocketmine\ddui\element\options\SliderElementOptions;
use pocketmine\ddui\element\options\TextFieldOptions;
use pocketmine\ddui\element\options\ToggleOptions;
use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function strtolower;

final class DebugCommand extends VanillaCommand{
	private const CLASS_NAMES = [
		0 => "Warrior",
		1 => "Mage",
		2 => "Ranger",
	];

	public function __construct(){
		parent::__construct(
			"debug",
			"Open Beelty debug tools",
			"/debug [ddui|messagebox|menu|help]",
			["dbg"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_DEBUG);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		$mode = strtolower($args[0] ?? "menu");

		return match($mode){
			"", "menu" => $this->handleMenuMode($sender),
			"ddui" => $this->handleDduiMode($sender),
			"messagebox", "msgbox" => $this->handleMessageBoxMode($sender),
			"help" => $this->handleHelpMode($sender),
			default => throw new InvalidCommandSyntaxException(),
		};
	}

	private function handleMenuMode(CommandSender $sender) : bool{
		if(!$sender instanceof Player){
			$this->sendDebugHelp($sender);
			return true;
		}

		$this->openDebugMenu($sender);
		return true;
	}

	private function handleDduiMode(CommandSender $sender) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "The DDUI debug test can only be used in-game.");
			return true;
		}

		$this->openDduiFormTest($sender);
		return true;
	}

	private function handleMessageBoxMode(CommandSender $sender) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "The DDUI message box test can only be used in-game.");
			return true;
		}

		$this->openDduiMessageBoxTest($sender);
		return true;
	}

	private function handleHelpMode(CommandSender $sender) : bool{
		$this->sendDebugHelp($sender);
		return true;
	}

	private function sendDebugHelp(CommandSender $sender) : void{
		$sender->sendMessage(TextFormat::GOLD . "Debug commands:");
		$sender->sendMessage(TextFormat::YELLOW . "/debug" . TextFormat::GRAY . " - Open the safe debug menu.");
		$sender->sendMessage(TextFormat::YELLOW . "/debug ddui" . TextFormat::GRAY . " - Open the experimental DDUI custom form test.");
		$sender->sendMessage(TextFormat::YELLOW . "/debug messagebox" . TextFormat::GRAY . " - Open the experimental DDUI message box test.");
	}

	private function openDebugMenu(Player $player) : void{
		$player->sendForm(new DebugSimpleMenuForm(
			title: "Debug Menu",
			content: "Choose a debug action.\n\nDDUI tests are experimental and may disconnect unsupported clients.",
			buttons: [
				[
					"text" => "Open DDUI Form Test",
					"handler" => fn(Player $viewer) => $this->openDduiFormTest($viewer)
				],
				[
					"text" => "Open DDUI Message Box",
					"handler" => fn(Player $viewer) => $this->openDduiMessageBoxTest($viewer)
				]
			]
		));
	}

	private function openDduiMessageBoxTest(Player $player) : void{
		$pressCount = DDUI::observable(0);
		$body = DDUI::observable("Press Ping to update this DDUI message box reactively.");

		$pressCount->subscribe(function(mixed $value) use ($body){
			$body->setValue("Primary button pressed " . (int) $value . " time(s).");
			return null;
		});

		DDUI::messageBox()
			->title("DDUI Message Box Test")
			->body($body)
			->button1("Ping", function(Player $viewer) use ($pressCount) : void{
				$nextValue = (int) $pressCount->getValue() + 1;
				$pressCount->setValue($nextValue);
				$viewer->sendMessage(TextFormat::GREEN . "DDUI message box primary button triggered (" . $nextValue . ").");
			}, "Update the body text")
			->button2("Back", fn(Player $viewer) => $this->openDebugMenu($viewer), "Return to the debug menu")
			->show($player);
	}

	private function openDduiFormTest(Player $player) : void{
		$name = DDUI::observable($player->getName());
		$bio = DDUI::observable("Builder");
		$level = DDUI::observable(12);
		$rank = DDUI::observable("Adventurer");
		$hardcore = DDUI::observable(false);
		$classIndex = DDUI::observable(0);
		$summary = DDUI::observable("");

		$refreshDerivedState = function() use ($name, $bio, $level, $rank, $hardcore, $classIndex, $summary) : void{
			$rank->setValue($this->resolveRank((int) $level->getValue()));
			$summary->setValue($this->buildSummary(
				(string) $name->getValue(),
				(string) $bio->getValue(),
				(int) $level->getValue(),
				(bool) $hardcore->getValue(),
				(int) $classIndex->getValue()
			));
		};

		foreach([$name, $bio, $level, $hardcore, $classIndex] as $observable){
			$observable->subscribe(function() use ($refreshDerivedState){
				$refreshDerivedState();
				return null;
			});
		}

		$refreshDerivedState();

		$form = DDUI::customForm()
			->title("DDUI Debug Test")
			->header("Reactive Custom Form")
			->label($summary)
			->textField("Nickname", $name, new TextFieldOptions(
				description: "Try editing this field and watch the summary update live."
			))
			->textField("Bio", $bio, new TextFieldOptions(
				description: "This also feeds the summary label above."
			))
			->slider("Level", 1, 100, $level, new SliderElementOptions(
				description: "Rank is recalculated from this slider.",
				step: 1
			))
			->textField("Rank", $rank, new TextFieldOptions(
				description: "This field is server-calculated.",
				disabled: true
			))
			->toggle("Hardcore Mode", $hardcore, new ToggleOptions(
				description: "Boolean example bound to a DDUI toggle."
			))
			->dropdown("Class", $this->getClassItems(), $classIndex, new DropdownOptions(
				description: "Dropdown selection also updates the summary."
			))
			->spacer()
			->button("Reset", function(Player $viewer) use ($name, $bio, $level, $hardcore, $classIndex) : void{
				$name->setValue($viewer->getName());
				$bio->setValue("Builder");
				$level->setValue(12);
				$hardcore->setValue(false);
				$classIndex->setValue(0);
				$viewer->sendMessage(TextFormat::YELLOW . "DDUI debug values reset.");
			})
			->button("Confirm", function(Player $viewer) use ($name, $bio, $level, $rank, $hardcore, $classIndex) : void{
				$viewer->sendMessage(TextFormat::GREEN . "DDUI debug values confirmed:");
				$viewer->sendMessage(TextFormat::GRAY . "Name: " . TextFormat::WHITE . (string) $name->getValue());
				$viewer->sendMessage(TextFormat::GRAY . "Bio: " . TextFormat::WHITE . (string) $bio->getValue());
				$viewer->sendMessage(TextFormat::GRAY . "Level: " . TextFormat::WHITE . (string) $level->getValue());
				$viewer->sendMessage(TextFormat::GRAY . "Rank: " . TextFormat::WHITE . (string) $rank->getValue());
				$viewer->sendMessage(TextFormat::GRAY . "Hardcore: " . TextFormat::WHITE . ((bool) $hardcore->getValue() ? "Enabled" : "Disabled"));
				$viewer->sendMessage(TextFormat::GRAY . "Class: " . TextFormat::WHITE . $this->resolveClassName((int) $classIndex->getValue()));
				$this->openDebugMenu($viewer);
			})
			->closeButton(new CloseButtonOptions(label: "Close DDUI Test"));

		$form->show($player);
	}

	/**
	 * @return list<DropdownItem>
	 */
	private function getClassItems() : array{
		return [
			new DropdownItem("Warrior", "Front-line fighter"),
			new DropdownItem("Mage", "Spell-based damage"),
			new DropdownItem("Ranger", "Ranged specialist"),
		];
	}

	private function resolveRank(int $level) : string{
		return match(true){
			$level >= 80 => "Mythic",
			$level >= 50 => "Elite",
			$level >= 20 => "Veteran",
			default => "Adventurer",
		};
	}

	private function resolveClassName(int $classIndex) : string{
		return self::CLASS_NAMES[$classIndex] ?? "Unknown";
	}

	private function buildSummary(string $name, string $bio, int $level, bool $hardcore, int $classIndex) : string{
		$mode = $hardcore ? "Hardcore" : "Normal";
		$bioText = $bio !== "" ? $bio : "No bio";

		return "Player {$name} | {$this->resolveClassName($classIndex)} | Lv {$level} | {$mode} | {$bioText}";
	}
}

final class DebugSimpleMenuForm implements Form{
	/**
	 * @param list<array{text: string, handler: callable(Player) : void}> $buttons
	 */
	public function __construct(
		private string $title,
		private string $content,
		private array $buttons
	){}

	public function jsonSerialize() : array{
		return [
			"type" => "form",
			"title" => $this->title,
			"content" => $this->content,
			"buttons" => array_map(
				static fn(array $button) => ["text" => $button["text"]],
				$this->buttons
			)
		];
	}

	public function handleResponse(Player $player, $data) : void{
		if($data === null){
			return;
		}

		if(!is_int($data) || !isset($this->buttons[$data])){
			throw new FormValidationException("Invalid debug menu response");
		}

		($this->buttons[$data]["handler"])($player);
	}
}
