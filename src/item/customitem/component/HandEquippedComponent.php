<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class HandEquippedComponent implements ItemComponent{
	
	public function __construct(
		private bool $value
	){}
	
	public function getName() : string{
		return "minecraft:hand_equipped";
	}
	
	public function getValue() : bool{
		return $this->value;
	}
	
	public function isHandEquipped() : bool{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
