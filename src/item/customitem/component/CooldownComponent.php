<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class CooldownComponent implements ItemComponent{
	
	public function __construct(
		private string $category,
		private float $duration
	){}
	
	public function getName() : string{
		return "minecraft:cooldown";
	}
	
	public function getValue() : array{
		return [
			"category" => $this->category,
			"duration" => $this->duration
		];
	}
	
	public function getCategory() : string{
		return $this->category;
	}
	
	public function getDuration() : float{
		return $this->duration;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
