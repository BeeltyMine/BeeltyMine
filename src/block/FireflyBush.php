<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\math\Facing;
use pocketmine\world\World;
use pocketmine\world\sound\FireflyBushAmbientSound;
use function mt_rand;

final class FireflyBush extends Flowable{
	use StaticSupportTrait;

	public function ticksRandomly() : bool{
		return true;
	}

	public function onRandomTick() : void{
		$world = $this->position->getWorld();
		$time = $world->getTime() % World::TIME_FULL;
		if($time < World::TIME_NIGHT || $time >= World::TIME_SUNRISE || mt_rand(1, 12) !== 1){
			return;
		}

		$above = $this->getSide(Facing::UP);
		if(!$above instanceof Leaves && $above->isSolid()){
			return;
		}

		$world->addSound($this->position->add(0.5, 0.5, 0.5), new FireflyBushAmbientSound());
	}

	private function canBeSupportedAt(Block $block) : bool{
		$supportBlock = $block->getSide(Facing::DOWN);
		return $supportBlock->hasTypeTag(BlockTypeTags::DIRT) ||
			$supportBlock->hasTypeTag(BlockTypeTags::MUD) ||
			$supportBlock->hasTypeTag(BlockTypeTags::MOSS);
	}

	public function getFlameEncouragement() : int{
		return 60;
	}

	public function getFlammability() : int{
		return 100;
	}
}
