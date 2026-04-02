<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\utils\NotCloneable;
use pocketmine\utils\NotSerializable;

class ArmorTrimPattern{
	use NotCloneable;
	use NotSerializable;

	private Item $item;

	public function __construct(Item $item){
		$this->item = clone $item;
	}

	public function getItem() : Item{
		return clone $this->item;
	}
}