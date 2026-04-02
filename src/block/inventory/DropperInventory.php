<?php

declare(strict_types=1);

namespace pocketmine\block\inventory;

use pocketmine\world\Position;

class DropperInventory extends DispenserInventory{
	public function __construct(Position $holder){
		parent::__construct($holder);
	}
}
