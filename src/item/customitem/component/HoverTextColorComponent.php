<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class HoverTextColorComponent implements ItemComponent{
	
	public function __construct(
		private string $value
	){}
	
	public function getName() : string{
		return "minecraft:hover_text_color";
	}
	
	public function getValue() : string{
		return $this->value;
	}
	
	public function getColor() : string{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
