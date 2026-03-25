<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\properties\LongProperty;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\player\Player;

final class ButtonClickElement extends LongProperty{
	public function __construct(ObjectProperty $parent){
		parent::__construct("onClick", 0, $parent);
	}

	public function triggerListeners(Player $player, mixed $data) : void{
		if(is_int($data)){
			$this->setValue($data);
		}
		parent::triggerListeners($player, $data);
	}
}
