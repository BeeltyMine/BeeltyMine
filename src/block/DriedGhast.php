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

namespace pocketmine\block;

use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\HappyGhast;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\GhastSound;
use function mt_rand;

class DriedGhast extends Transparent{
	private const UPDATE_INTERVAL_TICKS = 40;
	private const MAX_REHYDRATION_LEVEL = 3;

	private int $rehydrationLevel = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, self::MAX_REHYDRATION_LEVEL, $this->rehydrationLevel);
	}

	public function getRehydrationLevel() : int{
		return $this->rehydrationLevel;
	}

	/** @return $this */
	public function setRehydrationLevel(int $rehydrationLevel) : self{
		if($rehydrationLevel < 0 || $rehydrationLevel > self::MAX_REHYDRATION_LEVEL){
			throw new \InvalidArgumentException("Rehydration level must be in range 0..." . self::MAX_REHYDRATION_LEVEL);
		}

		$this->rehydrationLevel = $rehydrationLevel;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		return [
			AxisAlignedBB::one()
				->contract(0.1875, 0, 0.1875)
				->trim(Facing::UP, 0.125)
		];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->rehydrationLevel = 0;
		$placed = parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
		if($placed){
			$this->scheduleHydrationTick();
		}

		return $placed;
	}

	public function onNearbyBlockChange() : void{
		$this->scheduleHydrationTick();
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$touchingWater = $this->isTouchingWater();
		$nextLevel = $this->rehydrationLevel;

		if($touchingWater){
			if($nextLevel < self::MAX_REHYDRATION_LEVEL){
				++$nextLevel;
			}
		}elseif($nextLevel > 0){
			--$nextLevel;
		}

		if($nextLevel !== $this->rehydrationLevel){
			$world->setBlock($this->position, $this->setRehydrationLevel($nextLevel));
		}

		if($touchingWater && $nextLevel >= self::MAX_REHYDRATION_LEVEL){
			$world->setBlock($this->position, VanillaBlocks::AIR());
			$happyGhast = new HappyGhast(Location::fromObject(
				$this->position->add(0.5, 1.0, 0.5),
				$world,
				mt_rand(0, 359),
				0
			));
			$happyGhast->spawnToAll();
			$world->addSound($this->position, new GhastSound());
			return;
		}

		if($touchingWater || $nextLevel > 0){
			$this->scheduleHydrationTick();
		}
	}

	private function scheduleHydrationTick() : void{
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, self::UPDATE_INTERVAL_TICKS);
	}

	private function isTouchingWater() : bool{
		$world = $this->position->getWorld();
		foreach(Facing::ALL as $face){
			if($world->getBlock($this->position->getSide($face)) instanceof Water){
				return true;
			}
		}

		return false;
	}
}
