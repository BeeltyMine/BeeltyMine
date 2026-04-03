<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\generator\structure\StructureRegistry;
use pocketmine\world\generator\structure\StructureType;
use pocketmine\world\World;
use function count;
use function implode;
use function sqrt;
use function strtolower;

final class LocateCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"locate",
			"Locate generated structures in the current dimension",
			"/locate <structure|list> [world]"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_LOCATE);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) < 1 || count($args) > 2){
			throw new InvalidCommandSyntaxException();
		}

		$structureArg = strtolower($args[0]);
		$world = $this->resolveWorld($sender, $args[1] ?? null);
		if($world === null){
			return true;
		}

		$available = StructureRegistry::getCommandNamesForWorld($world);
		if($structureArg === "list"){
			$sender->sendMessage(TextFormat::GREEN . "Structures for \"" . $world->getFolderName() . "\": " . (count($available) > 0 ? implode(", ", $available) : "none"));
			return true;
		}

		$type = StructureType::fromString($structureArg);
		if($type === null){
			$sender->sendMessage(TextFormat::RED . "Unknown structure \"" . $structureArg . "\". Available: " . implode(", ", StructureType::getAllCommandNames()));
			return true;
		}
		if($type->getDimensionId() !== $world->getDimensionId()){
			$sender->sendMessage(TextFormat::RED . $type->getDisplayName() . " is not available in world \"" . $world->getFolderName() . "\".");
			return true;
		}

		[$originX, $originZ] = $this->resolveOrigin($sender, $world);
		$located = StructureRegistry::locateNearest($world, $type, $originX, $originZ);
		if($located === null){
			$sender->sendMessage(TextFormat::RED . "No " . $type->getDisplayName() . " could be found for this world.");
			return true;
		}

		$distance = (int) round(sqrt((($located->x - $originX) ** 2) + (($located->z - $originZ) ** 2)));
		$sender->sendMessage(
			TextFormat::GREEN . $type->getDisplayName() . " located in \"" . $world->getFolderName() . "\" at X=" . $located->x . ", Z=" . $located->z .
			" (chunk " . $located->chunkX . ", " . $located->chunkZ . ", distance " . $distance . " blocks)"
		);
		return true;
	}

	private function resolveWorld(CommandSender $sender, ?string $worldName) : ?World{
		$worldManager = $sender->getServer()->getWorldManager();
		if($worldName === null){
			if($sender instanceof Player){
				return $sender->getWorld();
			}

			$sender->sendMessage(TextFormat::RED . "Console must specify a world.");
			return null;
		}

		$world = $worldManager->getWorldByName($worldName);
		if($world !== null){
			return $world;
		}
		if(!$worldManager->loadWorld($worldName, true)){
			$sender->sendMessage(TextFormat::RED . "World \"" . $worldName . "\" could not be loaded.");
			return null;
		}

		return $worldManager->getWorldByName($worldName);
	}

	/**
	 * @return array{int, int}
	 */
	private function resolveOrigin(CommandSender $sender, World $world) : array{
		if($sender instanceof Player && $sender->getWorld() === $world){
			return [$sender->getLocation()->getFloorX(), $sender->getLocation()->getFloorZ()];
		}

		$spawn = $world->getSpawnLocation();
		return [$spawn->getFloorX(), $spawn->getFloorZ()];
	}
}
