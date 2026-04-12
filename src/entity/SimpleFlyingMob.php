<?php

/*
 *     ____            ____        __  ____
 *    / __ )___  ___  / / /___  __/  |/  (_)___  ___
 *   / __  / _ \/ _ \/ / __/ / / / /|_/ / / __ \/ _ \
 *  / /_/ /  __/  __/ / /_/ /_/ / /  / / / / / /  __/
 * /_____/\___/\___/_/\__/\__, /_/  /_/_/_/ /_/\___/
 *                       /____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Ayrz
 * @team BeeltyMine
 * 
 * 
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\math\Vector3;
use function atan2;
use function mt_rand;
use function sqrt;
use const M_PI;

abstract class SimpleFlyingMob extends Living{
	private ?Vector3 $flightDirection = null;
	private int $retargetTicks = 0;

	protected function getInitialGravity() : float{
		return 0.0;
	}

	protected function calculateFallDamage(float $fallDistance) : float{
		return 0;
	}

	public function canBreathe() : bool{
		return true;
	}

	abstract protected function getFlightSpeed() : float;

	protected function getVerticalMotionScale() : float{
		return 0.45;
	}

	protected function getRetargetInterval() : int{
		return 60;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isAlive()){
			return $hasUpdate;
		}

		$this->setHasGravity(false);

		$this->retargetTicks -= $tickDiff;
		if($this->flightDirection === null || $this->retargetTicks <= 0){
			$this->flightDirection = $this->createFlightDirection();
			$this->retargetTicks = $this->getRetargetInterval();
		}

		$direction = $this->flightDirection;
		if($direction !== null){
			$motion = $this->motion->multiply(0.7)->addVector($direction->multiply($this->getFlightSpeed() * 0.3));
			if($this->isUnderwater() && $motion->y < 0.08){
				$motion = $motion->withComponents(null, 0.08, null);
			}

			$this->motion = $motion;
			$horizontal = sqrt(($motion->x ** 2) + ($motion->z ** 2));
			if($horizontal > self::MOTION_THRESHOLD){
				$this->setRotation(
					-atan2($motion->x, $motion->z) * 180 / M_PI,
					-atan2($horizontal, $motion->y) * 180 / M_PI
				);
			}
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	private function createFlightDirection() : Vector3{
		$x = mt_rand(-1000, 1000) / 1000;
		$y = mt_rand(-1000, 1000) / 1000 * $this->getVerticalMotionScale();
		$z = mt_rand(-1000, 1000) / 1000;

		$direction = new Vector3($x, $y, $z);
		if($direction->lengthSquared() <= 0.0001){
			return new Vector3(0, 0.1, 0);
		}

		return $direction->normalize();
	}
}
