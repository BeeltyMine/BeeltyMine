<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class BundleInteractionComponent implements ItemComponent{
	
	public function __construct(
		private int $numViewableSlots = 12
	){}
	
	public function getName() : string{
		return "minecraft:bundle_interaction";
	}
	
	public function getValue() : array{
		return [
			"num_viewable_slots" => $this->numViewableSlots
		];
	}
	
	public function getNumViewableSlots() : int{
		return $this->numViewableSlots;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
