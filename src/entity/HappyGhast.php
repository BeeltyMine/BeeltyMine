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
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function min;
use function mt_rand;

class HappyGhast extends SimpleFlyingMob implements Ageable{
	private const TAG_BABY = "Baby";
	private const TAG_AGE = "Age";
	private const BABY_GROW_TICKS = 24000;
	private const BABY_SCALE = 0.5;

	private bool $baby = false;
	private int $ageTicks = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::HAPPY_GHAST; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(3.0, 3.0);
	}

	public function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
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
		return "Happy Ghast";
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

		if($this->baby && $item->getTypeId() === VanillaItems::SNOWBALL()->getTypeId()){
			$player->ignoreChargeableClickAirForTicks(1);
			$player->resetItemCooldown($item, 2);

			$this->ageTicks = min($this->ageTicks + (int) (self::BABY_GROW_TICKS * 0.1), self::BABY_GROW_TICKS);
			if($this->ageTicks >= self::BABY_GROW_TICKS){
				$this->setBaby(false);
				$this->ageTicks = 0;
			}
			$this->getWorld()->broadcastPacketToViewers(
				$this->location,
				ActorEventPacket::create($this->getId(), ActorEvent::BABY_ANIMAL_FEED, 0)
			);
			if(!$player->isCreative()){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
			}
			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	protected function getFlightSpeed() : float{
		return 0.09;
	}

	protected function getVerticalMotionScale() : float{
		return 0.35;
	}

	public function getXpDropAmount() : int{
		return $this->baby ? 0 : 1;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->baby && $this->isAlive()){
			$this->ageTicks += $tickDiff;
			if($this->ageTicks >= self::BABY_GROW_TICKS){
				$this->setBaby(false);
				$this->ageTicks = 0;
			}
		}

		return $hasUpdate;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::HAPPY_GHAST_SPAWN_EGG();
	}
}
