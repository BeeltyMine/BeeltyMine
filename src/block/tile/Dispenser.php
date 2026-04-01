<?php

declare(strict_types=1);

namespace pocketmine\block\tile;

use pocketmine\block\inventory\DispenserInventory;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;

class Dispenser extends Spawnable implements Container, Nameable{
	use NameableTrait;
	use ContainerTrait;

	protected DispenserInventory $inventory;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new DispenserInventory($this->position);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadName($nbt);
		$this->loadItems($nbt);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveName($nbt);
		$this->saveItems($nbt);
	}

	public function close() : void{
		if(!$this->closed){
			$this->inventory->removeAllViewers();
			parent::close();
		}
	}

	public function getInventory() : DispenserInventory{
		return $this->inventory;
	}

	public function getRealInventory() : DispenserInventory{
		return $this->inventory;
	}

	public function getDefaultName() : string{
		return "Dispenser";
	}
}
