<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class PaleHangingMoss extends Flowable{
	use StaticSupportTrait;

	private bool $tip = true;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->tip);
	}

	public function isTip() : bool{
		return $this->tip;
	}

	/** @return $this */
	public function setTip(bool $tip) : self{
		$this->tip = $tip;
		return $this;
	}

	private function canBeSupportedAt(Block $block) : bool{
		$above = $block->getSide(Facing::UP);
		return $above instanceof self || $block->getAdjacentSupportType(Facing::UP)->hasCenterSupport();
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this)){
			$this->position->getWorld()->useBreakOn($this->position);
			return;
		}

		$newTip = !($this->getSide(Facing::DOWN) instanceof self);
		if($newTip !== $this->tip){
			$this->tip = $newTip;
			$this->position->getWorld()->setBlock($this->position, $this, false);
		}
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
		$world = $this->position->getWorld();
		$this->tip = false;
		$world->setBlock($this->position, $this, false);
		$world->setBlock($below->position, VanillaBlocks::PALE_HANGING_MOSS()->setTip(true));
		return true;
	}

	public function getFlameEncouragement() : int{
		return 60;
	}

	public function getFlammability() : int{
		return 100;
	}
}
