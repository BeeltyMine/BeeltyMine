<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

final class Beehive extends Opaque implements HorizontalFacing{
	use HorizontalFacingTrait;

	private int $honeyLevel = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->boundedIntAuto(0, 5, $this->honeyLevel);
	}

	public function getHoneyLevel() : int{
		return $this->honeyLevel;
	}

	/** @return $this */
	public function setHoneyLevel(int $honeyLevel) : self{
		if($honeyLevel < 0 || $honeyLevel > 5){
			throw new \InvalidArgumentException("Honey level must be in range 0 ... 5");
		}
		$this->honeyLevel = $honeyLevel;
		return $this;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = Facing::opposite($player->getHorizontalFacing());
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function getFuelTime() : int{
		return 300;
	}
}
