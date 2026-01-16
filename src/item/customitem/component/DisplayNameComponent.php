<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class DisplayNameComponent implements ItemComponent{
	
	public function __construct(
		private string $value
	){}
	
	public function getName() : string{
		return "minecraft:display_name";
	}
	
	public function getValue() : array{
		return [
			"value" => $this->value
		];
	}
	
	public function getDisplayName() : string{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
