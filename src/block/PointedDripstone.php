<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\PointedDripstoneThickness;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

final class PointedDripstone extends Transparent{
	private PointedDripstoneThickness $thickness = PointedDripstoneThickness::TIP;
	private bool $hanging = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->enum($this->thickness);
		$w->bool($this->hanging);
	}

	public function getThickness() : PointedDripstoneThickness{
		return $this->thickness;
	}

	/** @return $this */
	public function setThickness(PointedDripstoneThickness $thickness) : self{
		$this->thickness = $thickness;
		return $this;
	}

	public function isHanging() : bool{
		return $this->hanging;
	}

	/** @return $this */
	public function setHanging(bool $hanging) : self{
		$this->hanging = $hanging;
		return $this;
	}

	private function canBeSupportedAt(Block $block) : bool{
		$support = $block->getSide($this->hanging ? Facing::UP : Facing::DOWN);
		return $support->getSupportType($this->hanging ? Facing::DOWN : Facing::UP) === SupportType::FULL || $support instanceof self;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($face !== Facing::UP && $face !== Facing::DOWN){
			return false;
		}
		$this->hanging = $face === Facing::DOWN;
		$this->thickness = PointedDripstoneThickness::TIP;
		if(!$this->canBeSupportedAt($blockReplace)){
			return false;
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this)){
			$this->position->getWorld()->useBreakOn($this->position);
		}
	}

	protected function recalculateCollisionBoxes() : array{
		$box = AxisAlignedBB::one();
		$box->squash(Axis::X, 6 / 16);
		$box->squash(Axis::Z, 6 / 16);
		$box->trim($this->hanging ? Facing::DOWN : Facing::UP, 8 / 16);
		return [$box];
	}
}
