<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class EnchantableValueComponent implements ItemComponent{
	
	public function __construct(
		private int $value
	){}
	
	public function getName() : string{
		return "minecraft:enchantable";
	}
	
	public function getValue() : array{
		return [
			"value" => $this->value
		];
	}
	
	public function getEnchantableValue() : int{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
