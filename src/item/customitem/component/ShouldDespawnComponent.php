<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class ShouldDespawnComponent implements ItemComponent{
	
	public function __construct(
		private bool $value
	){}
	
	public function getName() : string{
		return "minecraft:should_despawn";
	}
	
	public function getValue() : bool{
		return $this->value;
	}
	
	public function shouldDespawn() : bool{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
