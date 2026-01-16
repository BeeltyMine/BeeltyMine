<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class ThrowableComponent implements ItemComponent{
	
	public function __construct(
		private bool $doSwingAnimation = false,
		private float $launchPowerScale = 1.0,
		private float $maxDrawDuration = 0.0,
		private float $maxLaunchPower = 1.0,
		private float $minLaunchPower = 0.1,
		private bool $scaleThrowPowerByDrawDuration = false
	){}
	
	public function getName() : string{
		return "minecraft:throwable";
	}
	
	public function getValue() : array{
		return [
			"do_swing_animation" => $this->doSwingAnimation,
			"launch_power_scale" => $this->launchPowerScale,
			"max_draw_duration" => $this->maxDrawDuration,
			"max_launch_power" => $this->maxLaunchPower,
			"min_launch_power" => $this->minLaunchPower,
			"scale_power_by_draw_duration" => $this->scaleThrowPowerByDrawDuration
		];
	}
	
	public function doSwingAnimation() : bool{
		return $this->doSwingAnimation;
	}
	
	public function getLaunchPowerScale() : float{
		return $this->launchPowerScale;
	}
	
	public function getMaxDrawDuration() : float{
		return $this->maxDrawDuration;
	}
	
	public function getMaxLaunchPower() : float{
		return $this->maxLaunchPower;
	}
	
	public function getMinLaunchPower() : float{
		return $this->minLaunchPower;
	}
	
	public function scaleThrowPowerByDrawDuration() : bool{
		return $this->scaleThrowPowerByDrawDuration;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
