<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\utils\NotCloneable;
use pocketmine\utils\NotSerializable;

class ArmorTrimMaterial{
	use NotCloneable;
	use NotSerializable;

	private Item $item;

	public function __construct(Item $item, private readonly string $color){
		$this->item = clone $item;
	}

	public function getItem() : Item{
		return clone $this->item;
	}

	public function getColor() : string{
		return $this->color;
	}
}