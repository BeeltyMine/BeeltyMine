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
use pocketmine\item\SpawnEgg;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function atan2;
use function min;
use function mt_rand;
use const M_PI;

class Sniffer extends Living implements Ageable{
	private const TAG_BABY = "Baby";
	private const TAG_AGE = "Age";
	private const BABY_GROW_TICKS = 20 * 60 * 20;
	private const BABY_SCALE = 0.5;

	protected bool $baby = false;
	private int $ageTicks = 0;

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
		$this->baby = $nbt->getByte(self::TAG_BABY, 0) !== 0;
		$this->ageTicks = $nbt->getInt(self::TAG_AGE, 0);

		if($this->baby){
			$this->setScale(self::BABY_SCALE);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_BABY, $this->baby ? 1 : 0);
		$nbt->setInt(self::TAG_AGE, $this->ageTicks);
		return $nbt;
	}

	public function getName() : string{
		return "Sniffer";
	}

	public function isBaby() : bool{
		return $this->baby;
	}

	public function setBaby(bool $baby = true) : void{
		$this->baby = $baby;
		$this->setScale($baby ? self::BABY_SCALE : 1.0);
		$this->networkPropertiesDirty = true;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($item instanceof SpawnEgg){
			$baby = new self(Location::fromObject($this->location->add(0, 0.5, 0), $this->getWorld(), mt_rand(0, 360), 0));
			$baby->setBaby();
			$baby->spawnToAll();
			if(!$player->isCreative()){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
			}
			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->baby){
			$this->ageTicks = min($this->ageTicks + $tickDiff, self::BABY_GROW_TICKS);
			if($this->ageTicks >= self::BABY_GROW_TICKS){
				$this->setBaby(false);
				$this->ageTicks = 0;
			}
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
		return $this->baby ? 0 : 3;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SNIFFER_SPAWN_EGG();
	}
}
