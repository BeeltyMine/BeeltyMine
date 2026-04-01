<?php

/*
 *
 *  ____            _ _         __  __ _            
 * |  _ \          | | |       |  \/  (_)           
 * | |_) | ___  ___| | |_ _   _| \  / |_ _ __   ___ 
 * |  _ < / _ \/ _ \ | __| | | | |\/| | | '_ \ / _ \
 * | |_) |  __/  __/ | |_| |_| | |  | | | | | |  __/
 * |____/ \___|\___|_|\__|\__, |_|  |_|_|_| |_|\___|
 *                         __/ |                    
 *                        |___/                     
 *    _  _
 *   | )/ )
 *  \\ |//,' __
 * (")(_)-"()))=- BeeltyMine Team @ Since Ayrz
 *   (\\
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\inventory\DispenserInventory;
use pocketmine\block\tile\Dispenser as TileDispenser;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\RedstonePowerHelper;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\FlintSteel;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\LiquidBucket;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\BlazeShootSound;
use pocketmine\world\sound\FlintSteelSound;
use function array_rand;
use function count;

class Dispenser extends Opaque implements AnyFacing{
	use AnyFacingTrait;

	protected bool $triggered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->triggered);
	}

	public function isTriggered() : bool{
		return $this->triggered;
	}

	/** @return $this */
	public function setTriggered(bool $triggered) : self{
		$this->triggered = $triggered;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			if(
				abs($player->getPosition()->x - $blockReplace->position->x) < 2 &&
				abs($player->getPosition()->z - $blockReplace->position->z) < 2
			){
				$playerY = $player->getPosition()->y + $player->getEyeHeight();
				if($playerY - $blockReplace->position->y > 2){
					$this->facing = Facing::UP;
				}elseif($blockReplace->position->y - $playerY > 0){
					$this->facing = Facing::DOWN;
				}else{
					$this->facing = Facing::opposite($player->getHorizontalFacing());
				}
			}else{
				$this->facing = Facing::opposite($player->getHorizontalFacing());
			}
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player instanceof Player){
			$world = $this->position->getWorld();
			$tile = $world->getTile($this->position);
			if(!$tile instanceof TileDispenser){
				$world->setBlock($this->position, clone $this, false);
				$tile = $world->getTile($this->position);
			}

			if($tile instanceof TileDispenser){
				if(!$tile->canOpenWith($item->getCustomName())){
					return true;
				}
				$player->setCurrentWindow($tile->getInventory());
				return true;
			}

			return false;
		}

		return true;
	}

	public function onPostPlace() : void{
		$this->onNearbyBlockChange();
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		$receivingPower = RedstonePowerHelper::getStrongestNeighborPower($current) > 0;
		if($receivingPower){
			if(!$current->triggered){
				$world->setBlock($this->position, (clone $current)->setTriggered(true), false);
				$world->scheduleDelayedBlockUpdate($this->position, 4);
			}
		}else{
			if($current->triggered){
				$world->setBlock($this->position, (clone $current)->setTriggered(false), false);
			}
		}
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		$current->dispense();
	}

	protected function dispense() : void{
		$tile = $this->position->getWorld()->getTile($this->position);
		if(!$tile instanceof TileDispenser){
			return;
		}

		$inventory = $tile->getInventory();
		$slotMap = $inventory->getContents();
		if(count($slotMap) === 0){
			return;
		}

		$slots = array_keys($slotMap);
		$slot = $slots[array_rand($slots)];
		$item = $inventory->getItem($slot);
		if($item->isNull()){
			return;
		}

		if($this->tryDispenseSpecialItem($inventory, $slot, $item)){
			return;
		}

		$dispensed = $item->pop();
		$inventory->setItem($slot, $item);
		$this->ejectItem($dispensed);
	}

	protected function tryDispenseSpecialItem(DispenserInventory $inventory, int $slot, Item $item) : bool{
		if($item->getTypeId() === ItemTypeIds::BUCKET){
			return $this->tryDispenseEmptyBucket($inventory, $slot, $item);
		}

		if($item instanceof LiquidBucket){
			return $this->tryDispenseLiquidBucket($inventory, $slot, $item);
		}

		if($item instanceof FlintSteel){
			return $this->tryDispenseFlintAndSteel($inventory, $slot, $item);
		}

		if($item->getTypeId() === ItemTypeIds::FIRE_CHARGE){
			return $this->tryDispenseFireCharge($inventory, $slot, $item);
		}

		return false;
	}

	private function tryDispenseEmptyBucket(DispenserInventory $inventory, int $slot, Item $item) : bool{
		$world = $this->position->getWorld();
		$targetPos = $this->position->getSide($this->facing);
		$targetBlock = $world->getBlock($targetPos);
		if(!$targetBlock instanceof Liquid || !$targetBlock->isSource()){
			return false;
		}

		$filledBucket = match($targetBlock->getTypeId()){
			BlockTypeIds::WATER => VanillaItems::WATER_BUCKET(),
			BlockTypeIds::LAVA => VanillaItems::LAVA_BUCKET(),
			default => null
		};
		if($filledBucket === null){
			return false;
		}

		$world->setBlock($targetPos, VanillaBlocks::AIR());
		$world->addSound($targetPos->add(0.5, 0.5, 0.5), $targetBlock->getBucketFillSound());

		$remaining = clone $item;
		$remaining->pop();
		if($remaining->isNull()){
			$inventory->setItem($slot, $filledBucket);
		}else{
			$inventory->setItem($slot, $remaining);
			foreach($inventory->addItem($filledBucket) as $leftover){
				$this->ejectItem($leftover);
			}
		}

		return true;
	}

	private function tryDispenseLiquidBucket(DispenserInventory $inventory, int $slot, LiquidBucket $item) : bool{
		$world = $this->position->getWorld();
		$targetPos = $this->position->getSide($this->facing);
		$targetBlock = $world->getBlock($targetPos);
		if(!$targetBlock->canBeReplaced()){
			return false;
		}

		$liquid = clone $item->getLiquid();
		$world->setBlock($targetPos, $liquid->getFlowingForm());
		$world->addSound($targetPos->add(0.5, 0.5, 0.5), $liquid->getBucketEmptySound());
		$inventory->setItem($slot, VanillaItems::BUCKET());

		return true;
	}

	private function tryDispenseFlintAndSteel(DispenserInventory $inventory, int $slot, FlintSteel $item) : bool{
		$world = $this->position->getWorld();
		$targetPos = $this->position->getSide($this->facing);
		$targetBlock = $world->getBlock($targetPos);

		if(($targetBlock instanceof TNT || $targetBlock instanceof Campfire) && $targetBlock->onInteract($item, Facing::opposite($this->facing), new Vector3(0.5, 0.5, 0.5), null)){
			$inventory->setItem($slot, $item);
			return true;
		}

		if($targetBlock->getTypeId() !== BlockTypeIds::AIR){
			return false;
		}

		$world->setBlock($targetPos, VanillaBlocks::FIRE());
		$world->addSound($targetPos->add(0.5, 0.5, 0.5), new FlintSteelSound());
		$item->applyDamage(1);
		$inventory->setItem($slot, $item);

		return true;
	}

	private function tryDispenseFireCharge(DispenserInventory $inventory, int $slot, Item $item) : bool{
		$world = $this->position->getWorld();
		$targetPos = $this->position->getSide($this->facing);
		$targetBlock = $world->getBlock($targetPos);

		if(($targetBlock instanceof TNT || $targetBlock instanceof Campfire) && $targetBlock->onInteract($item, Facing::opposite($this->facing), new Vector3(0.5, 0.5, 0.5), null)){
			$inventory->setItem($slot, $item);
			return true;
		}

		if($targetBlock->getTypeId() !== BlockTypeIds::AIR){
			return false;
		}

		$world->setBlock($targetPos, VanillaBlocks::FIRE());
		$world->addSound($targetPos->add(0.5, 0.5, 0.5), new BlazeShootSound());
		$item->pop();
		$inventory->setItem($slot, $item);

		return true;
	}

	protected function ejectItem(Item $item) : void{
		if($item->isNull()){
			return;
		}

		[$x, $y, $z] = Facing::OFFSET[$this->facing];
		$spawnPos = $this->position->add(0.5 + ($x * 0.7), 0.5 + ($y * 0.7), 0.5 + ($z * 0.7));
		$motion = new Vector3($x * 0.2, 0.1 + ($y * 0.2), $z * 0.2);
		$this->position->getWorld()->dropItem($spawnPos, $item, $motion);
	}
}
