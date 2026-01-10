<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class DurabilityComponent implements ItemComponent{
	
	public function __construct(
		private int $maxDurability,
		private int $damageChance = 100
	){}
	
	public function getName() : string{
		return "minecraft:durability";
	}
	
	public function getValue() : array{
		return [
			"max_durability" => $this->maxDurability,
			"damage_chance" => [
				"min" => $this->damageChance,
				"max" => $this->damageChance
			]
		];
	}
	
	public function getMaxDurability() : int{
		return $this->maxDurability;
	}
	
	public function getDamageChance() : int{
		return $this->damageChance;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
