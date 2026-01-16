<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class MaxStackSizeComponent implements ItemComponent{
	
	public function __construct(
		private int $value
	){}
	
	public function getName() : string{
		return "minecraft:max_stack_size";
	}
	
	public function getValue() : int{
		return $this->value;
	}
	
	public function getMaxStackSize() : int{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
