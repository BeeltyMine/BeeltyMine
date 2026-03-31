<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;

final class MossCarpet extends Flowable{
	use StaticSupportTrait;

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getSide(Facing::DOWN)->getTypeId() !== BlockTypeIds::AIR;
	}

	public function isSolid() : bool{
		return true;
	}

	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::UP, 15 / 16)];
	}

	public function getFlameEncouragement() : int{
		return 0;
	}

	public function getFlammability() : int{
		return 0;
	}
}
