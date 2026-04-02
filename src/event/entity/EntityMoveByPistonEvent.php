<?php

declare(strict_types=1);

namespace pocketmine\event\entity;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;

class EntityMoveByPistonEvent extends EntityMotionEvent{
	public function __construct(Entity $entity, Vector3 $mot){
		parent::__construct($entity, $mot);
	}
}
