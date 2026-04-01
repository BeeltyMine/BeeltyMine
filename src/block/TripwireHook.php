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

namespace pocketmine\block;

use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\World;
use function count;

class TripwireHook extends Flowable implements HorizontalFacing{
	use HorizontalFacingTrait;

	public const MAX_WIRE_LENGTH = 40;
	private const NETWORK_UPDATE_DELAY_TICKS = 1;

	protected bool $connected = false;
	protected bool $powered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->connected);
		$w->bool($this->powered);
	}

	public function isConnected() : bool{ return $this->connected; }

	/** @return $this */
	public function setConnected(bool $connected) : self{
		$this->connected = $connected;
		return $this;
	}

	public function isPowered() : bool{ return $this->powered; }

	/** @return $this */
	public function setPowered(bool $powered) : self{
		$this->powered = $powered;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(Facing::axis($face) === Axis::Y || !$this->canBeSupportedAt($blockReplace, Facing::opposite($face))){
			return false;
		}

		$this->facing = $face;
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onPostPlace() : void{
		$this->scheduleNetworkUpdate();
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this, Facing::opposite($this->facing))){
			$this->position->getWorld()->useBreakOn($this->position);
			return;
		}

		$this->scheduleNetworkUpdate();
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		if(!$current->canBeSupportedAt($current, Facing::opposite($current->facing))){
			$world->useBreakOn($this->position);
			return;
		}

		$current->updateNetworkState();
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->scheduleOppositeHookUpdate();
		return parent::onBreak($item, $player, $returnedItems);
	}

	public static function scheduleHooksForTripwirePosition(World $world, Vector3 $tripwirePos) : void{
		foreach(Facing::HORIZONTAL as $direction){
			for($distance = 1; $distance <= self::MAX_WIRE_LENGTH; ++$distance){
				$next = $world->getBlock($tripwirePos->getSide($direction, $distance));
				if($next instanceof Tripwire){
					continue;
				}

				if($next instanceof self && $next->getFacing() === Facing::opposite($direction)){
					$world->scheduleDelayedBlockUpdate($next->position, self::NETWORK_UPDATE_DELAY_TICKS);
				}
				break;
			}
		}
	}

	private function scheduleNetworkUpdate() : void{
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, self::NETWORK_UPDATE_DELAY_TICKS);
	}

	private function canBeSupportedAt(Block $block, int $face) : bool{
		return $block->getAdjacentSupportType($face)->hasCenterSupport();
	}

	private function scheduleOppositeHookUpdate() : void{
		$world = $this->position->getWorld();
		for($distance = 1; $distance <= self::MAX_WIRE_LENGTH; ++$distance){
			$next = $world->getBlock($this->position->getSide($this->facing, $distance));
			if($next instanceof Tripwire){
				continue;
			}

			if($next instanceof self && $next->getFacing() === Facing::opposite($this->facing)){
				$world->scheduleDelayedBlockUpdate($next->position, self::NETWORK_UPDATE_DELAY_TICKS);
			}
			break;
		}
	}

	private function updateNetworkState() : void{
		$world = $this->position->getWorld();

		$tripwires = [];
		$oppositeHook = null;
		$hasTriggeredWire = false;

		for($distance = 1; $distance <= self::MAX_WIRE_LENGTH; ++$distance){
			$next = $world->getBlock($this->position->getSide($this->facing, $distance));
			if($next instanceof Tripwire){
				$tripwires[] = $next;
				if($next->isTriggered() && !$next->isDisarmed()){
					$hasTriggeredWire = true;
				}
				continue;
			}

			if($next instanceof self && $next->getFacing() === Facing::opposite($this->facing)){
				$oppositeHook = $next;
			}

			break;
		}

		$connected = $oppositeHook !== null && count($tripwires) > 0;
		$powered = $connected && $hasTriggeredWire;

		$notifyThis = false;
		if($this->connected !== $connected || $this->powered !== $powered){
			$world->setBlock($this->position, (clone $this)->setConnected($connected)->setPowered($powered), false);
			$notifyThis = true;
		}

		$notifyOther = false;
		if($oppositeHook !== null && ($oppositeHook->connected !== $connected || $oppositeHook->powered !== $powered)){
			$world->setBlock($oppositeHook->position, (clone $oppositeHook)->setConnected($connected)->setPowered($powered), false);
			$notifyOther = true;
		}

		foreach($tripwires as $wire){
			if($wire->isConnected() !== $connected){
				$world->setBlock($wire->position, (clone $wire)->setConnected($connected), false);
			}
		}

		if($notifyThis){
			$world->notifyNeighbourBlockUpdate($this->position);
		}

		if($notifyOther && $oppositeHook !== null){
			$world->notifyNeighbourBlockUpdate($oppositeHook->position);
		}
	}
}
