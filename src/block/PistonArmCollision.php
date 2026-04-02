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
use pocketmine\block\utils\SupportType;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\player\Player;

class PistonArmCollision extends Transparent implements AnyFacing{
	use AnyFacingTrait;

	public function canBePlaced() : bool{
		return false;
	}

	public function isSolid() : bool{
		return false;
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$world = $this->position->getWorld();

		$backPos = $this->position->getSide(Facing::opposite($this->facing));
		$back = $world->getBlock($backPos);
		if($back instanceof Piston && ($back->getFacing() === $this->facing || Facing::opposite($back->getFacing()) === $this->facing)){
			$world->setBlock($backPos, VanillaBlocks::AIR(), true);
			return parent::onBreak($item, $player, $returnedItems);
		}

		$frontPos = $this->position->getSide($this->facing);
		$front = $world->getBlock($frontPos);
		if($front instanceof Piston && ($front->getFacing() === $this->facing || Facing::opposite($front->getFacing()) === $this->facing)){
			$world->setBlock($frontPos, VanillaBlocks::AIR(), true);
		}

		return parent::onBreak($item, $player, $returnedItems);
	}
}
