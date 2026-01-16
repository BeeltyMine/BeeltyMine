<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class AllowOffHandComponent implements ItemComponent{
	
	public function __construct(
		private bool $value
	){}
	
	public function getName() : string{
		return "minecraft:allow_off_hand";
	}
	
	public function getValue() : bool{
		return $this->value;
	}
	
	public function isAllowed() : bool{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
