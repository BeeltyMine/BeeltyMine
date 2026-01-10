<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class LiquidClippedComponent implements ItemComponent{
	
	public function __construct(
		private bool $value
	){}
	
	public function getName() : string{
		return "minecraft:liquid_clipped";
	}
	
	public function getValue() : bool{
		return $this->value;
	}
	
	public function isLiquidClipped() : bool{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
