<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class DamageAbsorptionComponent implements ItemComponent{
	
	public function __construct(
		private float $absorbAmount
	){}
	
	public function getName() : string{
		return "minecraft:damage_absorption";
	}
	
	public function getValue() : array{
		return [
			"absorb_amount" => $this->absorbAmount
		];
	}
	
	public function getAbsorbAmount() : float{
		return $this->absorbAmount;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
