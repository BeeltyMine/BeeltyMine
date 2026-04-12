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
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use function atan2;
use function mt_rand;
use const M_PI;

class Sniffer extends Living implements Ageable{
	protected bool $baby = false;

	private int $wanderCooldown = 0;
	private float $wanderX = 0.0;
	private float $wanderZ = 0.0;

	public static function getNetworkTypeId() : string{ return EntityIds::SNIFFER; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.75, 1.9);
	}

	public function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(14);
		parent::initEntity($nbt);
	}

	public function getName() : string{
		return "Sniffer";
	}

	public function isBaby() : bool{
		return $this->baby;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		$this->wanderCooldown -= $tickDiff;
		if($this->wanderCooldown <= 0){
			$this->wanderCooldown = mt_rand(40, 100);
			$this->wanderX = mt_rand(-1000, 1000) / 1000;
			$this->wanderZ = mt_rand(-1000, 1000) / 1000;
		}

		if($this->onGround){
			$this->motion = $this->motion->withComponents($this->wanderX * 0.08, null, $this->wanderZ * 0.08);
			if(($this->wanderX ** 2 + $this->wanderZ ** 2) > 0.0001){
				$this->setRotation(-atan2($this->wanderX, $this->wanderZ) * 180 / M_PI, $this->location->pitch);
			}
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
	}

	public function getXpDropAmount() : int{
		return 3;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SNIFFER_SPAWN_EGG();
	}
}
