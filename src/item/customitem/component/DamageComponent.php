<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class DamageComponent implements ItemComponent{
	
	public function __construct(
		private int $damage
	){}
	
	public function getName() : string{
		return "minecraft:damage";
	}
	
	public function getValue() : int{
		return $this->damage;
	}
	
	public function getDamage() : int{
		return $this->damage;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
