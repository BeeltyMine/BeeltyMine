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

use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
use pocketmine\block\utils\RedstonePowerHelper;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use function max;

class RedstoneWire extends Flowable implements AnalogRedstoneSignalEmitter{
	use AnalogRedstoneSignalEmitterTrait;
	use StaticSupportTrait {
		onNearbyBlockChange as onSupportBlockChange;
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		//TODO: check connections to nearby redstone components

		return $this;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN)->hasCenterSupport();
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		$this->onSupportBlockChange();

		if(!$world->getBlock($this->position) instanceof self){
			return;
		}

		$world->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		$newSignalStrength = $current->calculateSignalStrength();
		if($newSignalStrength !== $current->signalStrength){
			$world->setBlock($this->position, (clone $current)->setOutputSignalStrength($newSignalStrength));
			$current->scheduleConnectedWireUpdates();
		}
	}

	private function scheduleConnectedWireUpdates() : void{
		$world = $this->position->getWorld();
		$queued = [];

		foreach(Facing::HORIZONTAL as $face){
			$sidePos = $this->position->getSide($face);
			$this->scheduleWireUpdateAt($sidePos, $queued);

			$side = $world->getBlock($sidePos);

			if(!$this->getSide(Facing::UP)->isSolid()){
				$this->scheduleWireUpdateAt($sidePos->getSide(Facing::UP), $queued);
			}

			if(!$side->isSolid()){
				$this->scheduleWireUpdateAt($sidePos->getSide(Facing::DOWN), $queued);
			}
		}
	}

	/**
	 * @param array<string, true> $queued
	 */
	private function scheduleWireUpdateAt(Vector3 $position, array &$queued) : void{
		$key = (int) $position->x . ':' . (int) $position->y . ':' . (int) $position->z;
		if(isset($queued[$key])){
			return;
		}

		$world = $this->position->getWorld();
		if($world->getBlock($position) instanceof self){
			$queued[$key] = true;
			$world->scheduleDelayedBlockUpdate($position, 1);
		}
	}

	private function calculateSignalStrength() : int{
		$directPower = RedstonePowerHelper::getStrongestNeighborPower($this, true);
		$maxWirePower = 0;

		foreach(Facing::HORIZONTAL as $face){
			$maxWirePower = max($maxWirePower, $this->getAdjacentWirePower($face));
		}

		return max($directPower, max(0, $maxWirePower - 1));
	}

	private function getAdjacentWirePower(int $face) : int{
		$side = $this->getSide($face);
		$power = $side instanceof self ? $side->getOutputSignalStrength() : 0;

		if(!$this->getSide(Facing::UP)->isSolid()){
			$upSide = $side->getSide(Facing::UP);
			if($upSide instanceof self){
				$power = max($power, $upSide->getOutputSignalStrength());
			}
		}

		if(!$side->isSolid()){
			$downSide = $side->getSide(Facing::DOWN);
			if($downSide instanceof self){
				$power = max($power, $downSide->getOutputSignalStrength());
			}
		}

		return $power;
	}

	public function asItem() : Item{
		return VanillaItems::REDSTONE_DUST();
	}
}
