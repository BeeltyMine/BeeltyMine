<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class FuelComponent implements ItemComponent{
	
	public function __construct(
		private float $duration
	){}
	
	public function getName() : string{
		return "minecraft:fuel";
	}
	
	public function getValue() : array{
		return [
			"duration" => $this->duration
		];
	}
	
	public function getDuration() : float{
		return $this->duration;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
