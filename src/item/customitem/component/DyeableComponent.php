<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class DyeableComponent implements ItemComponent{
	
	public function __construct(
		private int $defaultColor = 0xFFFFFF
	){}
	
	public function getName() : string{
		return "minecraft:dyeable";
	}
	
	public function getValue() : array{
		return [
			"default_color" => $this->defaultColor
		];
	}
	
	public function getDefaultColor() : int{
		return $this->defaultColor;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
