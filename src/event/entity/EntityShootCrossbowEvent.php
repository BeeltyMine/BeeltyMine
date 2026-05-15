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

namespace pocketmine\event\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\item\Item;
use pocketmine\utils\Utils;
use function count;

/**
 * @phpstan-extends EntityEvent<Living>
 */
final class EntityShootCrossbowEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	/** @phpstan-param non-empty-list<Entity> $projectiles */
	public function __construct(
		Living $shooter,
		private Item $crossbow,
		private array $projectiles
	){
		$this->entity = $shooter;
	}

	public function getCrossbow() : Item{
		return $this->crossbow;
	}

	/**
	 * @return Entity[]
	 * @phpstan-return non-empty-list<Entity>
	 */
	public function getProjectiles() : array{
		return $this->projectiles;
	}

	/**
	 * @param Entity[] $projectiles
	 * @phpstan-param non-empty-list<Entity> $projectiles
	 */
	public function setProjectiles(array $projectiles) : void{
		Utils::validateArrayValueType($projectiles, function(Entity $_) : void{});
		if(count($projectiles) === 0){
			throw new \LogicException("Crossbow must shoot at least one projectile");
		}

		$this->projectiles = $projectiles;
	}
}
