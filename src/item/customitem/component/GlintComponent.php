<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class GlintComponent implements ItemComponent{
	
	public function __construct(
		private bool $value
	){}
	
	public function getName() : string{
		return "minecraft:glint";
	}
	
	public function getValue() : bool{
		return $this->value;
	}
	
	public function hasGlint() : bool{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
