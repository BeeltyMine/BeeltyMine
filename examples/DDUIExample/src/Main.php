<?php

declare(strict_types=1);

namespace beelty\dduiexample;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\ddui\CustomForm;
use pocketmine\ddui\Observable;
use pocketmine\ddui\element\DropdownItem;
use pocketmine\ddui\element\options\CloseButtonOptions;
use pocketmine\ddui\element\options\DropdownOptions;
use pocketmine\ddui\element\options\SliderElementOptions;
use pocketmine\ddui\element\options\TextFieldOptions;
use pocketmine\ddui\element\options\ToggleOptions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

final class Main extends PluginBase{
	private const CLASS_NAMES = [
		0 => "Warrior",
		1 => "Mage",
		2 => "Ranger",
	];

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($command->getName() !== "ddui-example"){
			return false;
		}

		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
			return true;
		}

		$this->openExampleForm($sender);
		return true;
	}

	private function openExampleForm(Player $player) : void{
		$name = new Observable($player->getName());
		$bio = new Observable("Builder");
		$level = new Observable(12);
		$rank = new Observable("Adventurer");
		$hardcore = new Observable(false);
		$classIndex = new Observable(0);
		$summary = new Observable("");

		$refreshDerivedState = function() use ($name, $bio, $level, $rank, $hardcore, $classIndex, $summary) : void{
			$rank->setValue($this->resolveRank((int) $level->getValue()));
			$summary->setValue(
				$this->buildSummary(
					(string) $name->getValue(),
					(string) $bio->getValue(),
					(int) $level->getValue(),
					(bool) $hardcore->getValue(),
					(int) $classIndex->getValue()
				)
			);
		};

		$name->subscribe(function() use ($refreshDerivedState){
			$refreshDerivedState();
			return null;
		});
		$bio->subscribe(function() use ($refreshDerivedState){
			$refreshDerivedState();
			return null;
		});
		$level->subscribe(function() use ($refreshDerivedState){
			$refreshDerivedState();
			return null;
		});
		$hardcore->subscribe(function() use ($refreshDerivedState){
			$refreshDerivedState();
			return null;
		});
		$classIndex->subscribe(function() use ($refreshDerivedState){
			$refreshDerivedState();
			return null;
		});

		$refreshDerivedState();

		$form = (new CustomForm("DDUI Example"))
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
				$viewer->sendMessage(TextFormat::YELLOW . "DDUI example reset.");
			})
			->button("Confirm", function(Player $viewer) use ($name, $bio, $level, $rank, $hardcore, $classIndex, &$form) : void{
				$viewer->sendMessage(TextFormat::GREEN . "DDUI values confirmed:");
				$viewer->sendMessage(TextFormat::GRAY . "Name: " . TextFormat::WHITE . (string) $name->getValue());
				$viewer->sendMessage(TextFormat::GRAY . "Bio: " . TextFormat::WHITE . (string) $bio->getValue());
				$viewer->sendMessage(TextFormat::GRAY . "Level: " . TextFormat::WHITE . (string) $level->getValue());
				$viewer->sendMessage(TextFormat::GRAY . "Rank: " . TextFormat::WHITE . (string) $rank->getValue());
				$viewer->sendMessage(TextFormat::GRAY . "Hardcore: " . TextFormat::WHITE . ((bool) $hardcore->getValue() ? "Enabled" : "Disabled"));
				$viewer->sendMessage(TextFormat::GRAY . "Class: " . TextFormat::WHITE . $this->resolveClassName((int) $classIndex->getValue()));
				$form->close($viewer);
			})
			->closeButton(new CloseButtonOptions(label: "Close Example"));

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
		$bioText = trim($bio) !== "" ? $bio : "No bio";

		return "Player {$name} | {$this->resolveClassName($classIndex)} | Lv {$level} | {$mode} | {$bioText}";
	}
}
