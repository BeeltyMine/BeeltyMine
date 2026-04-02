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

namespace pocketmine\block\tile;

use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\beacon\BeaconStructure;
use pocketmine\world\World;

final class Beacon extends Spawnable{
	private const TAG_LEVELS = "Levels"; //TAG_Int
	private const TAG_PRIMARY = "primary"; //TAG_Int
	private const TAG_SECONDARY = "secondary"; //TAG_Int

	private int $primaryEffect = 0;
	private int $secondaryEffect = 0;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$world->getServer()->getBeaconManager()->registerBeacon($this);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_LEVELS, $this->getCurrentLevel());
		$nbt->setInt(self::TAG_PRIMARY, $this->primaryEffect);
		$nbt->setInt(self::TAG_SECONDARY, $this->secondaryEffect);
	}

	private function getCurrentLevel() : int{
		$world = $this->position->getWorld();
		$pos = $this->position;

		return BeaconStructure::calculateLevel(function(int $layer, int $offsetX, int $offsetZ) use ($world, $pos) : bool{
			return BeaconStructure::isValidBaseBlockTypeId($world->getBlockAt($pos->getFloorX() + $offsetX, $pos->getFloorY() - $layer, $pos->getFloorZ() + $offsetZ)->getTypeId());
		});
	}

	public function readSaveData(CompoundTag $nbt) : void{
		//TODO: PC uses Primary and Secondary (capitalized first letter), we don't read them here because the IDs would be different
		$this->primaryEffect = $nbt->getInt(self::TAG_PRIMARY, 0);
		$this->secondaryEffect = $nbt->getInt(self::TAG_SECONDARY, 0);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_PRIMARY, $this->primaryEffect);
		$nbt->setInt(self::TAG_SECONDARY, $this->secondaryEffect);
	}

	public function getPrimaryEffect() : int{ return $this->primaryEffect; }

	public function setPrimaryEffect(int $primaryEffect) : void{
		$this->primaryEffect = $primaryEffect;
		$this->clearSpawnCompoundCache();
		$this->position->getWorld()->getServer()->getBeaconManager()->invalidate();
	}

	public function getSecondaryEffect() : int{ return $this->secondaryEffect; }

	public function setSecondaryEffect(int $secondaryEffect) : void{
		$this->secondaryEffect = $secondaryEffect;
		$this->clearSpawnCompoundCache();
		$this->position->getWorld()->getServer()->getBeaconManager()->invalidate();
	}

	public function close() : void{
		if(!$this->closed){
			$this->position->getWorld()->getServer()->getBeaconManager()->unregisterBeacon($this);
		}
		parent::close();
	}
}
