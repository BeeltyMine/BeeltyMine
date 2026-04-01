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

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\player\Player;

class Tripwire extends Flowable{
	private const DEACTIVATION_DELAY_TICKS = 10;

	protected bool $triggered = false;
	protected bool $suspended = false; //unclear usage, makes hitbox bigger if set
	protected bool $connected = false;
	protected bool $disarmed = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->triggered);
		$w->bool($this->suspended);
		$w->bool($this->connected);
		$w->bool($this->disarmed);
	}

	public function isTriggered() : bool{ return $this->triggered; }

	/** @return $this */
	public function setTriggered(bool $triggered) : self{
		$this->triggered = $triggered;
		return $this;
	}

	public function isSuspended() : bool{ return $this->suspended; }

	/** @return $this */
	public function setSuspended(bool $suspended) : self{
		$this->suspended = $suspended;
		return $this;
	}

	public function isConnected() : bool{ return $this->connected; }

	/** @return $this */
	public function setConnected(bool $connected) : self{
		$this->connected = $connected;
		return $this;
	}

	public function isDisarmed() : bool{ return $this->disarmed; }

	/** @return $this */
	public function setDisarmed(bool $disarmed) : self{
		$this->disarmed = $disarmed;
		return $this;
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onPostPlace() : void{
		$this->notifyAttachedHooks();
	}

	public function onNearbyBlockChange() : void{
		$this->notifyAttachedHooks();
	}

	public function onEntityInside(Entity $entity) : bool{
		if(!self::canEntityTrigger($entity)){
			return true;
		}

		$world = $this->position->getWorld();
		if(!$this->triggered){
			$world->setBlock($this->position, (clone $this)->setTriggered(true), false);
			$world->notifyNeighbourBlockUpdate($this->position);
			$this->notifyAttachedHooks();
		}

		$world->scheduleDelayedBlockUpdate($this->position, self::DEACTIVATION_DELAY_TICKS);
		return true;
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		$triggered = $current->hasTriggeringEntities();
		if($current->triggered !== $triggered){
			$world->setBlock($this->position, (clone $current)->setTriggered($triggered), false);
			$world->notifyNeighbourBlockUpdate($this->position);
			$current->notifyAttachedHooks();
		}

		if($triggered){
			$world->scheduleDelayedBlockUpdate($this->position, self::DEACTIVATION_DELAY_TICKS);
		}
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->notifyAttachedHooks();
		return parent::onBreak($item, $player, $returnedItems);
	}

	public function asItem() : Item{
		return VanillaItems::STRING();
	}

	private function notifyAttachedHooks() : void{
		TripwireHook::scheduleHooksForTripwirePosition($this->position->getWorld(), $this->position);
	}

	private function hasTriggeringEntities() : bool{
		foreach($this->position->getWorld()->getNearbyEntities($this->getActivationBox()) as $entity){
			if(self::canEntityTrigger($entity)){
				return true;
			}
		}

		return false;
	}

	private function getActivationBox() : AxisAlignedBB{
		return AxisAlignedBB::one()
			->trim(Facing::UP, 15 / 16)
			->offset($this->position->x, $this->position->y, $this->position->z);
	}

	private static function canEntityTrigger(Entity $entity) : bool{
		return !($entity instanceof Player && $entity->isSpectator());
	}
}
