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

use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\RedstonePowerHelper;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function max;

class RedstoneRepeater extends Flowable implements PoweredByRedstone, HorizontalFacing{
	use HorizontalFacingTrait;
	use PoweredByRedstoneTrait;
	use StaticSupportTrait {
		onNearbyBlockChange as onSupportBlockChange;
	}

	public const MIN_DELAY = 1;
	public const MAX_DELAY = 4;

	protected int $delay = self::MIN_DELAY;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->boundedIntAuto(self::MIN_DELAY, self::MAX_DELAY, $this->delay);
		$w->bool($this->powered);
	}

	public function getDelay() : int{ return $this->delay; }

	/** @return $this */
	public function setDelay(int $delay) : self{
		if($delay < self::MIN_DELAY || $delay > self::MAX_DELAY){
			throw new \InvalidArgumentException("Delay must be in range " . self::MIN_DELAY . " ... " . self::MAX_DELAY);
		}
		$this->delay = $delay;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::UP, 7 / 8)];
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = Facing::opposite($player->getHorizontalFacing());
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(++$this->delay > self::MAX_DELAY){
			$this->delay = self::MIN_DELAY;
		}
		$world = $this->position->getWorld();
		$world->setBlock($this->position, $this);
		$world->scheduleDelayedBlockUpdate($this->position, $this->delay * 2);
		return true;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN) !== SupportType::NONE;
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		$this->onSupportBlockChange();

		if(!$world->getBlock($this->position) instanceof self){
			return;
		}

		$world->scheduleDelayedBlockUpdate($this->position, $this->delay * 2);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		if($current->isLocked()){
			return;
		}

		$shouldBePowered = $current->getRearInputPower() > 0;
		if($shouldBePowered !== $current->powered){
			$world->setBlock($this->position, (clone $current)->setPowered($shouldBePowered));
		}
	}

	private function isLocked() : bool{
		return $this->getSideInputPower() > 0;
	}

	private function getRearInputPower() : int{
		return RedstonePowerHelper::getPowerFromFace($this, $this->facing);
	}

	private function getSideInputPower() : int{
		$right = Facing::rotateY($this->facing, true);
		$left = Facing::rotateY($this->facing, false);

		return max(
			RedstonePowerHelper::getPowerFromFace($this, $right),
			RedstonePowerHelper::getPowerFromFace($this, $left)
		);
	}
}
