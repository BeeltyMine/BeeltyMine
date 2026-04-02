<?php

declare(strict_types=1);

namespace pocketmine\event\block;

use pocketmine\block\Block;
use pocketmine\block\Piston;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;

class BlockPistonEvent extends BlockEvent implements Cancellable{
	use CancellableTrait;

	/** @var Block[] */
	private array $movedBlocks;
	/** @var Block[] */
	private array $destroyedBlocks;

	/**
	 * @param Block[] $movedBlocks
	 * @param Block[] $destroyedBlocks
	 */
	public function __construct(Piston $piston, private int $direction, array $movedBlocks, array $destroyedBlocks, private bool $extending){
		parent::__construct($piston);
		$this->movedBlocks = $movedBlocks;
		$this->destroyedBlocks = $destroyedBlocks;
	}

	public function getDirection() : int{
		return $this->direction;
	}

	/**
	 * @return Block[]
	 */
	public function getMovedBlocks() : array{
		return $this->movedBlocks;
	}

	/**
	 * @return Block[]
	 */
	public function getDestroyedBlocks() : array{
		return $this->destroyedBlocks;
	}

	public function isExtending() : bool{
		return $this->extending;
	}

	public function getBlock() : Piston{
		/** @var Piston $block */
		$block = parent::getBlock();
		return $block;
	}
}
