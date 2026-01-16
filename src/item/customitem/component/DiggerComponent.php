<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class DiggerComponent implements ItemComponent{
	
	/**
	 * @param array<string, int|float> $destroySpeeds
	 */
	public function __construct(
		private array $destroySpeeds = [],
		private bool $useEfficiency = false
	){}
	
	public function getName() : string{
		return "minecraft:digger";
	}
	
	public function getValue() : array{
		$blocks = [];
		foreach($this->destroySpeeds as $blockName => $speed){
			$blocks[] = [
				"block" => $blockName,
				"speed" => $speed
			];
		}
		
		return [
			"destroy_speeds" => $blocks,
			"use_efficiency" => $this->useEfficiency
		];
	}
	
	/**
	 * @return array<string, int|float>
	 */
	public function getDestroySpeeds() : array{
		return $this->destroySpeeds;
	}
	
	public function useEfficiency() : bool{
		return $this->useEfficiency;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
