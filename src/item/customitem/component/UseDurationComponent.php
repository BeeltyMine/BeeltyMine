<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class UseDurationComponent implements ItemComponent{
	
	public function __construct(
		private float $value
	){}
	
	public function getName() : string{
		return "minecraft:use_duration";
	}
	
	public function getValue() : float{
		return $this->value;
	}
	
	public function getDuration() : float{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
