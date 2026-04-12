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

use pocketmine\block\utils\CrackedState;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Location;
use pocketmine\entity\Sniffer;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function mt_rand;

class SnifferEgg extends Transparent{
	private const UPDATE_INTERVAL_TICKS = 1200;

	protected CrackedState $crackedState = CrackedState::NO_CRACKS;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->enum($this->crackedState);
	}

	public function getCrackedState() : CrackedState{
		return $this->crackedState;
	}

	/** @return $this */
	public function setCrackedState(CrackedState $crackedState) : self{
		$this->crackedState = $crackedState;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		return [
			AxisAlignedBB::one()
				->contract(0.125, 0, 0.125)
				->trim(Facing::UP, 0.125)
		];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->crackedState = CrackedState::NO_CRACKS;
		$placed = parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
		if($placed){
			$this->scheduleHatchTick();
		}

		return $placed;
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		if($this->crackedState !== CrackedState::MAX_CRACKED){
			$world->setBlock($this->position, $this->setCrackedState(match($this->crackedState){
				CrackedState::NO_CRACKS => CrackedState::CRACKED,
				CrackedState::CRACKED => CrackedState::MAX_CRACKED,
				CrackedState::MAX_CRACKED => CrackedState::MAX_CRACKED,
			}));
			$this->scheduleHatchTick();
			return;
		}

		$world->setBlock($this->position, VanillaBlocks::AIR());
		$sniffer = new Sniffer(Location::fromObject(
			$this->position->add(0.5, 0.5, 0.5),
			$world,
			mt_rand(0, 359),
			0
		));
		$sniffer->setBaby();
		$sniffer->spawnToAll();
	}

	private function scheduleHatchTick() : void{
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, self::UPDATE_INTERVAL_TICKS);
	}
}
