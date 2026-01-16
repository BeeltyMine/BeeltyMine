<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class EnchantableSlotComponent implements ItemComponent{
	
	public function __construct(
		private string $slot
	){}
	
	public function getName() : string{
		return "minecraft:enchantable";
	}
	
	public function getValue() : array{
		return [
			"slot" => $this->slot
		];
	}
	
	public function getSlot() : string{
		return $this->slot;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
