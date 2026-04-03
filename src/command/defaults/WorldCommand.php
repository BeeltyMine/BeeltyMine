<?php

/*
 *     ____            ____        __  ____
 *    / __ )___  ___  / / /___  __/  |/  (_)___  ___
 *   / __  / _ \/ _ \/ / __/ / / / /|_/ / / __ \/ _ \
 *  / /_/ /  __/  __/ / /_/ /_/ / /  / / / / / /  __/
 * /_____/\___/\___/_/\__/\__, /_/  /_/_/_/ /_/\___/
 *                       /____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Ayrz
 *
 */


declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\entity\Location;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\Position;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\WorldDimension;
use pocketmine\YmlServerProperties;
use function count;
use function implode;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

final class WorldCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"world",
			"Manage worlds for testing",
			"/world <list|create|load|tp> ...",
			["worlds"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_WORLD);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		$server = $sender->getServer();
		$worldManager = $server->getWorldManager();

		$subCommand = strtolower($args[0] ?? "");
		switch($subCommand){
			case "list":
				if(count($args) !== 1){
					throw new InvalidCommandSyntaxException();
				}

				$lines = [];
				foreach($worldManager->getWorlds() as $world){
					$lines[] = $world->getFolderName() . " [" . WorldDimension::dimensionIdToString($world->getDimensionId()) . "] players=" . count($world->getPlayers());
				}
				$sender->sendMessage(TextFormat::GREEN . "Loaded worlds: " . (count($lines) > 0 ? implode(", ", $lines) : "none"));
				return true;

			case "create":
				if(count($args) < 2 || count($args) > 5){
					throw new InvalidCommandSyntaxException();
				}

				$name = trim($args[1]);
				if($name === ""){
					$sender->sendMessage(TextFormat::RED . "World name cannot be empty.");
					return true;
				}

				$generatorName = strtolower($args[2] ?? "default");
				$generatorEntry = GeneratorManager::getInstance()->getGenerator($generatorName);
				if($generatorEntry === null){
					$sender->sendMessage(TextFormat::RED . "Unknown generator \"" . $generatorName . "\". Available: " . implode(", ", GeneratorManager::getInstance()->getGeneratorList()));
					return true;
				}

				$seedString = $args[3] ?? null;
				$dimensionName = $args[4] ?? null;
				if($dimensionName !== null && WorldDimension::parseDimensionString($dimensionName) === null){
					$sender->sendMessage(TextFormat::RED . "Unknown dimension \"" . $dimensionName . "\". Use overworld, nether or end.");
					return true;
				}

				$configGroup = $server->getConfigGroup();
				$worldConfig = $configGroup->getProperty(YmlServerProperties::WORLDS, []);
				if(!is_array($worldConfig)){
					$worldConfig = [];
				}
				$previousConfig = $worldConfig[$name] ?? null;

				$resolvedDimensionName = $dimensionName ?? WorldDimension::dimensionIdToString(
					WorldDimension::resolveDimensionId($generatorName)
				);
				$worldConfig[$name] = [
					"generator" => $generatorName,
					"dimension" => $resolvedDimensionName
				];
				if($seedString !== null){
					$worldConfig[$name]["seed"] = $seedString;
				}
				$configGroup->setProperty(YmlServerProperties::WORLDS, $worldConfig);

				$options = WorldCreationOptions::create()
					->setGeneratorClass($generatorEntry->getGeneratorClass())
					->setDifficulty($server->getDifficulty());

				if($seedString !== null){
					$convertedSeed = Generator::convertSeed($seedString);
					if($convertedSeed !== null){
						$options->setSeed($convertedSeed);
					}
				}

				if(!$worldManager->generateWorld($name, $options)){
					if($previousConfig !== null){
						$worldConfig[$name] = $previousConfig;
					}else{
						unset($worldConfig[$name]);
					}
					$configGroup->setProperty(YmlServerProperties::WORLDS, $worldConfig);
					$sender->sendMessage(TextFormat::RED . "World \"" . $name . "\" could not be created. It may already exist.");
					return true;
				}

				$configGroup->save();
				$sender->sendMessage(TextFormat::GREEN . "World \"" . $name . "\" created with generator \"" . $generatorName . "\" in dimension \"" . $resolvedDimensionName . "\".");
				return true;

			case "load":
				if(count($args) !== 2){
					throw new InvalidCommandSyntaxException();
				}

				$name = $args[1];
				if($worldManager->isWorldLoaded($name)){
					$sender->sendMessage(TextFormat::YELLOW . "World \"" . $name . "\" is already loaded.");
					return true;
				}
				if(!$worldManager->loadWorld($name, true)){
					$sender->sendMessage(TextFormat::RED . "World \"" . $name . "\" could not be loaded.");
					return true;
				}

				$sender->sendMessage(TextFormat::GREEN . "World \"" . $name . "\" loaded.");
				return true;

			case "tp":
			case "goto":
				if(count($args) < 2 || count($args) > 3){
					throw new InvalidCommandSyntaxException();
				}

				$worldName = $args[1];
				$world = $worldManager->getWorldByName($worldName);
				if($world === null){
					if(!$worldManager->loadWorld($worldName, true)){
						$sender->sendMessage(TextFormat::RED . "World \"" . $worldName . "\" is not loaded and could not be loaded.");
						return true;
					}
					$world = $worldManager->getWorldByName($worldName);
				}
				if($world === null){
					$sender->sendMessage(TextFormat::RED . "World \"" . $worldName . "\" was not found.");
					return true;
				}

				$target = $this->resolveTeleportTarget($sender, $args[2] ?? null);
				if($target === null){
					return true;
				}

				$world->requestSafeSpawn()->onCompletion(
					function(Position $safeSpawn) use ($sender, $target, $world) : void{
						if(!$target->isConnected()){
							return;
						}

						$target->teleport(Location::fromObject($safeSpawn->add(0.5, 0, 0.5), $world));
						$sender->sendMessage(TextFormat::GREEN . "Teleported " . $target->getName() . " to world \"" . $world->getFolderName() . "\".");
						if($target !== $sender){
							$target->sendMessage(TextFormat::YELLOW . "You were teleported to world \"" . $world->getFolderName() . "\".");
						}
					},
					function() use ($sender, $worldName) : void{
						$sender->sendMessage(TextFormat::RED . "Could not find a safe spawn in world \"" . $worldName . "\".");
					}
				);
				return true;
		}

		throw new InvalidCommandSyntaxException();
	}

	private function resolveTeleportTarget(CommandSender $sender, ?string $targetName) : ?Player{
		if($targetName === null){
			if($sender instanceof Player){
				return $sender;
			}

			$sender->sendMessage(TextFormat::RED . "Console must specify a player.");
			return null;
		}

		$player = $sender->getServer()->getPlayerByPrefix($targetName);
		if($player === null){
			$sender->sendMessage(TextFormat::RED . "Player \"" . $targetName . "\" was not found.");
			return null;
		}

		return $player;
	}
}
