<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class WearableComponent implements ItemComponent{
	
	public const SLOT_ARMOR_HEAD = "slot.armor.head";
	public const SLOT_ARMOR_CHEST = "slot.armor.chest";
	public const SLOT_ARMOR_LEGS = "slot.armor.legs";
	public const SLOT_ARMOR_FEET = "slot.armor.feet";
	
	public function __construct(
		private string $slot,
		private int $protection = 0
	){}
	
	public function getName() : string{
		return "minecraft:wearable";
	}
	
	public function getValue() : array{
		return [
			"slot" => $this->slot,
			"protection" => $this->protection
		];
	}
	
	public function getSlot() : string{
		return $this->slot;
	}
	
	public function getProtection() : int{
		return $this->protection;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
