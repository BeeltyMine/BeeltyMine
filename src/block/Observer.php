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

use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Observer extends Opaque implements AnyFacing, PoweredByRedstone{
	use AnyFacingTrait;
	use PoweredByRedstoneTrait;

	private const PULSE_DELAY_TICKS = 2;
	private const MIN_TRIGGER_INTERVAL_TICKS = 6;

	/** @var string[] */
	private static array $lastObservedState = [];

	/** @var int[] */
	private static array $nextAllowedTriggerTick = [];

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->powered);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			if(
				abs($player->getPosition()->x - $blockReplace->position->x) < 2 &&
				abs($player->getPosition()->z - $blockReplace->position->z) < 2
			){
				$playerY = $player->getPosition()->y + $player->getEyeHeight();
				if($playerY - $blockReplace->position->y > 2){
					$this->facing = Facing::DOWN;
				}elseif($blockReplace->position->y - $playerY > 0){
					$this->facing = Facing::UP;
				}else{
					$this->facing = $player->getHorizontalFacing();
				}
			}else{
				$this->facing = $player->getHorizontalFacing();
			}
		}

		$placed = parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
		if($placed){
			self::$lastObservedState[$this->getCacheKey()] = $this->getObservedBlockSignature();
		}

		return $placed;
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		$cacheKey = $this->getCacheKey();
		$currentObservedState = $this->getObservedBlockSignature();
		$previousObservedState = self::$lastObservedState[$cacheKey] ?? null;
		self::$lastObservedState[$cacheKey] = $currentObservedState;

		if($previousObservedState === null){
			return;
		}

		// Observer should only pulse when the observed (front) block state actually changes.
		if($previousObservedState === $currentObservedState){
			return;
		}

		if($current->powered){
			return;
		}

		$currentTick = $world->getServer()->getTick();
		$nextAllowedTick = self::$nextAllowedTriggerTick[$cacheKey] ?? 0;
		if($currentTick < $nextAllowedTick){
			return;
		}

		self::$nextAllowedTriggerTick[$cacheKey] = $currentTick + self::MIN_TRIGGER_INTERVAL_TICKS;
		$world->scheduleDelayedBlockUpdate($this->position, self::PULSE_DELAY_TICKS);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		if($current->powered){
			$world->setBlock($this->position, (clone $current)->setPowered(false));
			return;
		}

		$world->setBlock($this->position, (clone $current)->setPowered(true));
		$world->scheduleDelayedBlockUpdate($this->position, self::PULSE_DELAY_TICKS);
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$cacheKey = $this->getCacheKey();
		unset(self::$lastObservedState[$cacheKey], self::$nextAllowedTriggerTick[$cacheKey]);
		return parent::onBreak($item, $player, $returnedItems);
	}

	private function getCacheKey() : string{
		$worldId = $this->position->getWorld()->getId();
		return $worldId . ':' . (int) $this->position->x . ':' . (int) $this->position->y . ':' . (int) $this->position->z;
	}

	private function getObservedBlockSignature() : string{
		$observedBlock = $this->getSide($this->facing);
		return $observedBlock->getTypeId() . ':' . $observedBlock->getStateId();
	}
}