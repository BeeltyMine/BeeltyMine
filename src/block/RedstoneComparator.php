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

use pocketmine\block\tile\Comparator;
use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
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
use function assert;
use function max;

class RedstoneComparator extends Flowable implements AnalogRedstoneSignalEmitter, PoweredByRedstone, HorizontalFacing{
	use HorizontalFacingTrait;
	use AnalogRedstoneSignalEmitterTrait;
	use PoweredByRedstoneTrait;
	use StaticSupportTrait;

	protected bool $isSubtractMode = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->isSubtractMode);
		$w->bool($this->powered);
	}

	public function readStateFromWorld() : Block{
		parent::readStateFromWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof Comparator){
			$this->signalStrength = $tile->getSignalStrength();
		}

		return $this;
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->position->getWorld()->getTile($this->position);
		assert($tile instanceof Comparator);
		$tile->setSignalStrength($this->signalStrength);
	}

	public function isSubtractMode() : bool{
		return $this->isSubtractMode;
	}

	/** @return $this */
	public function setSubtractMode(bool $isSubtractMode) : self{
		$this->isSubtractMode = $isSubtractMode;
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
		$this->isSubtractMode = !$this->isSubtractMode;
		$world = $this->position->getWorld();
		$world->setBlock($this->position, $this);
		$world->scheduleDelayedBlockUpdate($this->position, 2);
		return true;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN) !== SupportType::NONE;
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		parent::onNearbyBlockChange();

		if(!$world->getBlock($this->position) instanceof self){
			return;
		}

		$world->scheduleDelayedBlockUpdate($this->position, 2);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		$inputPower = $current->getRearInputPower();
		$sidePower = $current->getSideInputPower();
		$outputSignal = $current->isSubtractMode ? max(0, $inputPower - $sidePower) : ($inputPower >= $sidePower ? $inputPower : 0);
		$shouldBePowered = $outputSignal > 0;

		if($outputSignal !== $current->signalStrength || $shouldBePowered !== $current->powered){
			$world->setBlock(
				$this->position,
				(clone $current)
					->setOutputSignalStrength($outputSignal)
					->setPowered($shouldBePowered)
			);
		}
	}

	private function getRearInputPower() : int{
		$front = $this->getSide($this->facing);
		if($front instanceof AnalogRedstoneSignalEmitter){
			return $front->getOutputSignalStrength();
		}

		if($front->isSolid()){
			$through = $front->getSide($this->facing);
			if($through instanceof AnalogRedstoneSignalEmitter){
				return $through->getOutputSignalStrength();
			}
		}

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
