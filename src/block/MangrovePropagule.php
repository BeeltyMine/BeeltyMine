<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\event\block\StructureGrowEvent;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\generator\object\TreeFactory;
use pocketmine\world\generator\object\TreeType;
use function mt_rand;

final class MangrovePropagule extends Flowable{

	private const MAX_STAGE = 4;

	private int $stage = 0;
	private bool $hanging = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, self::MAX_STAGE, $this->stage);
		$w->bool($this->hanging);
	}

	public function getStage() : int{
		return $this->stage;
	}

	/** @return $this */
	public function setStage(int $stage) : self{
		if($stage < 0 || $stage > self::MAX_STAGE){
			throw new \InvalidArgumentException("Stage must be between 0 and " . self::MAX_STAGE);
		}
		$this->stage = $stage;
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

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		return ($this->canPlantAt($blockReplace) || $this->canHangAt($blockReplace)) &&
			parent::canBePlacedAt($blockReplace, $clickVector, $face, $isClickedBlock);
	}

	public function place(\pocketmine\world\BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->hanging = $face === Facing::DOWN && $blockClicked->getTypeId() === BlockTypeIds::MANGROVE_LEAVES;
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		$valid = $this->hanging ? $this->canHangAt($this) : $this->canPlantAt($this);
		if(!$valid){
			$this->position->getWorld()->useBreakOn($this->position);
			return;
		}
		parent::onNearbyBlockChange();
	}

	public function ticksRandomly() : bool{
		return true;
	}

	public function onRandomTick() : void{
		if($this->hanging){
			if($this->stage < self::MAX_STAGE && mt_rand(1, 7) === 1){
				$this->stage++;
				$this->position->getWorld()->setBlock($this->position, $this);
			}
			return;
		}

		$world = $this->position->getWorld();
		if($world->getFullLightAt($this->position->getFloorX(), $this->position->getFloorY(), $this->position->getFloorZ()) < 8 || mt_rand(1, 7) !== 1){
			return;
		}

		if($this->stage >= self::MAX_STAGE){
			$this->grow(null);
		}else{
			$this->stage = self::MAX_STAGE;
			$world->setBlock($this->position, $this);
		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!($item instanceof Fertilizer)){
			return false;
		}

		if($this->hanging){
			if($this->stage >= self::MAX_STAGE){
				return false;
			}
			$this->stage++;
			$this->position->getWorld()->setBlock($this->position, $this);
			$item->pop();
			return true;
		}

		$matured = false;
		if($this->stage < self::MAX_STAGE){
			$this->stage = self::MAX_STAGE;
			$this->position->getWorld()->setBlock($this->position, $this);
			$matured = true;
		}

		if($this->grow($player)){
			$item->pop();
			return true;
		}

		if($matured){
			$item->pop();
			return true;
		}

		return false;
	}

	private function grow(?Player $player) : bool{
		$random = new Random(mt_rand());
		$tree = TreeFactory::get($random, TreeType::MANGROVE);
		$transaction = $tree?->getBlockTransaction($this->position->getWorld(), $this->position->getFloorX(), $this->position->getFloorY(), $this->position->getFloorZ(), $random);
		if($transaction === null){
			return false;
		}

		$transaction->addBlockAt(
			$this->position->getFloorX(),
			$this->position->getFloorY(),
			$this->position->getFloorZ(),
			VanillaBlocks::AIR()
		);

		$ev = new StructureGrowEvent($this, $transaction, $player);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}

		return $transaction->apply();
	}

	private function canPlantAt(Block $block) : bool{
		$supportBlock = $block->getSide(Facing::DOWN);
		return $supportBlock->hasTypeTag(BlockTypeTags::DIRT) ||
			$supportBlock->hasTypeTag(BlockTypeTags::MUD) ||
			$supportBlock->hasTypeTag(BlockTypeTags::MOSS) ||
			$supportBlock->getTypeId() === BlockTypeIds::CLAY;
	}

	private function canHangAt(Block $block) : bool{
		return $block->getSide(Facing::UP)->getTypeId() === BlockTypeIds::MANGROVE_LEAVES;
	}

	public function getFuelTime() : int{
		return 100;
	}
}
