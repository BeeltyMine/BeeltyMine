<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class UseModifiersComponent implements ItemComponent{
	
	public function __construct(
		private float $useAnimationDuration = 0.0,
		private float $movementModifier = 1.0
	){}
	
	public function getName() : string{
		return "minecraft:use_modifiers";
	}
	
	public function getValue() : array{
		return [
			"use_duration" => $this->useAnimationDuration,
			"movement_modifier" => $this->movementModifier
		];
	}
	
	public function getUseAnimationDuration() : float{
		return $this->useAnimationDuration;
	}
	
	public function getMovementModifier() : float{
		return $this->movementModifier;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
