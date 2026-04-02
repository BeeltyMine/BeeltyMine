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

use pocketmine\block\utils\Lightable;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\RedstonePowerHelper;
use pocketmine\data\runtime\RuntimeDataDescriber;

class RedstoneLamp extends Opaque implements PoweredByRedstone, Lightable{
	use PoweredByRedstoneTrait;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->powered);
	}

	public function getLightLevel() : int{
		return $this->powered ? 15 : 0;
	}

	public function isLit() : bool{
		return $this->powered;
	}

	/** @return $this */
	public function setLit(bool $lit = true) : self{
		$this->powered = $lit;
		return $this;
	}

	public function onNearbyBlockChange() : void{
		$shouldBePowered = RedstonePowerHelper::getStrongestNeighborPower($this) > 0;
		if($shouldBePowered !== $this->powered){
			$this->position->getWorld()->setBlock($this->position, (clone $this)->setPowered($shouldBePowered));
		}
	}
}
