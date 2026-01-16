<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class ShooterComponent implements ItemComponent{
	
	public function __construct(
		private int $maxDrawDuration = 0,
		private bool $chargeOnDraw = false,
		private bool $scaleDrawDuration = false,
		private float $ammunition = 1.0
	){}
	
	public function getName() : string{
		return "minecraft:shooter";
	}
	
	public function getValue() : array{
		return [
			"max_draw_duration" => $this->maxDrawDuration,
			"charge_on_draw" => $this->chargeOnDraw,
			"scale_power_by_draw_duration" => $this->scaleDrawDuration,
			"ammunition" => $this->ammunition
		];
	}
	
	public function getMaxDrawDuration() : int{
		return $this->maxDrawDuration;
	}
	
	public function chargeOnDraw() : bool{
		return $this->chargeOnDraw;
	}
	
	public function scaleDrawDuration() : bool{
		return $this->scaleDrawDuration;
	}
	
	public function getAmmunition() : float{
		return $this->ammunition;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
