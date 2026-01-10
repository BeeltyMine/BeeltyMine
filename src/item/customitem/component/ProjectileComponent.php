<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class ProjectileComponent implements ItemComponent{
	
	public function __construct(
		private string $projectileEntity,
		private float $minimumCriticalPower = 0.9
	){}
	
	public function getName() : string{
		return "minecraft:projectile";
	}
	
	public function getValue() : array{
		return [
			"projectile_entity" => $this->projectileEntity,
			"minimum_critical_power" => $this->minimumCriticalPower
		];
	}
	
	public function getProjectileEntity() : string{
		return $this->projectileEntity;
	}
	
	public function getMinimumCriticalPower() : float{
		return $this->minimumCriticalPower;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
