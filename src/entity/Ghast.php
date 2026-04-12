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
 * @team BeeltyMine
 * 
 * 
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class Ghast extends SimpleFlyingMob{

	public static function getNetworkTypeId() : string{ return EntityIds::GHAST; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(4.0, 4.0);
	}

	public function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(10);
		parent::initEntity($nbt);
	}

	public function getName() : string{
		return "Ghast";
	}

	protected function getFlightSpeed() : float{
		return 0.12;
	}

	public function getDrops() : array{
		$looting = $this->getLootingLevelForDrops();
		$drops = [
			VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2 + $looting))
		];

		if(mt_rand(0, 1) === 1){
			$drops[] = VanillaItems::GHAST_TEAR()->setCount(mt_rand(1, 1 + $looting));
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::GHAST_SPAWN_EGG();
	}
}
