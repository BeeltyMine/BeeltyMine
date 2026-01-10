<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class InteractButtonComponent implements ItemComponent{
	
	public function __construct(
		private string $value
	){}
	
	public function getName() : string{
		return "minecraft:interact_button";
	}
	
	public function getValue() : string{
		return $this->value;
	}
	
	public function getButtonText() : string{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
