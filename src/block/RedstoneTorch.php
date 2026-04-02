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
use pocketmine\block\utils\LightableTrait;
use pocketmine\block\utils\RedstonePowerHelper;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\math\Facing;

class RedstoneTorch extends Torch implements Lightable{
	use LightableTrait;

	public function __construct(BlockIdentifier $idInfo, string $name, BlockTypeInfo $typeInfo){
		$this->lit = true;
		parent::__construct($idInfo, $name, $typeInfo);
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		parent::describeBlockOnlyState($w);
		$w->bool($this->lit);
	}

	public function getLightLevel() : int{
		return $this->lit ? 7 : 0;
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

		$shouldBeLit = !$current->isPoweredFromAttachedSide();
		if($shouldBeLit !== $current->lit){
			$world->setBlock($this->position, (clone $current)->setLit($shouldBeLit));
		}
	}

	private function isPoweredFromAttachedSide() : bool{
		$supportDirection = Facing::opposite($this->facing);
		$support = $this->getSide($supportDirection);

		return RedstonePowerHelper::getEmittedPowerTowards($support, $this->facing) > 0;
	}
}
