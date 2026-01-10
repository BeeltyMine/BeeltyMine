<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class RarityComponent implements ItemComponent{
	
	public const COMMON = "common";
	public const UNCOMMON = "uncommon";
	public const RARE = "rare";
	public const EPIC = "epic";
	
	public function __construct(
		private string $value
	){}
	
	public function getName() : string{
		return "minecraft:rarity";
	}
	
	public function getValue() : string{
		return $this->value;
	}
	
	public function getRarity() : string{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
