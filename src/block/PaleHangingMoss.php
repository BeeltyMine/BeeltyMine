<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class PaleHangingMoss extends Flowable{
	use StaticSupportTrait;

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::UP)->hasCenterSupport();
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!($item instanceof Fertilizer)){
			return false;
		}

		$below = $this->getSide(Facing::DOWN);
		if(!$below->canBeReplaced()){
			return false;
		}

		$item->pop();
		$this->position->getWorld()->setBlock($below->position, VanillaBlocks::PALE_HANGING_MOSS());
		return true;
	}

	public function getFlameEncouragement() : int{
		return 60;
	}

	public function getFlammability() : int{
		return 100;
	}
}
