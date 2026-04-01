<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\SaplingType;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\event\block\StructureGrowEvent;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\generator\object\BigJungleTree;
use pocketmine\world\generator\object\BigSpruceTree;
use pocketmine\world\generator\object\DarkOakTree;
use pocketmine\world\generator\object\TreeFactory;
use function mt_rand;

class Sapling extends Flowable{
	use StaticSupportTrait;

	protected bool $ready = false;

	private SaplingType $saplingType;

	public function __construct(BlockIdentifier $idInfo, string $name, BlockTypeInfo $typeInfo, SaplingType $saplingType){
		parent::__construct($idInfo, $name, $typeInfo);
		$this->saplingType = $saplingType;
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->ready);
	}

	public function isReady() : bool{ return $this->ready; }

	/** @return $this */
	public function setReady(bool $ready) : self{
		$this->ready = $ready;
		return $this;
	}

	private function canBeSupportedAt(Block $block) : bool{
		$supportBlock = $block->getSide(Facing::DOWN);
		return $supportBlock->hasTypeTag(BlockTypeTags::DIRT) ||
			$supportBlock->hasTypeTag(BlockTypeTags::MUD) ||
			$supportBlock->hasTypeTag(BlockTypeTags::MOSS);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($item instanceof Fertilizer && $this->grow($player)){
			$item->pop();

			return true;
		}

		return false;
	}

	public function ticksRandomly() : bool{
		return true;
	}

	public function onRandomTick() : void{
		$world = $this->position->getWorld();
		if($world->getFullLightAt($this->position->getFloorX(), $this->position->getFloorY(), $this->position->getFloorZ()) >= 8 && mt_rand(1, 7) === 1){
			if($this->ready){
				$this->grow(null);
			}else{
				$this->ready = true;
				$world->setBlock($this->position, $this);
			}
		}
	}

	private function grow(?Player $player) : bool{
		$random = new Random(mt_rand());
		$x = $this->position->getFloorX();
		$y = $this->position->getFloorY();
		$z = $this->position->getFloorZ();

		$tree = null;
		switch($this->saplingType){
			case SaplingType::SPRUCE:
				[$anchorX, $anchorZ] = $this->findClusterAnchor(SaplingType::SPRUCE);
				if($anchorX !== null && $anchorZ !== null){
					$x = $anchorX;
					$z = $anchorZ;
					$tree = new BigSpruceTree();
				}
				break;

			case SaplingType::JUNGLE:
				[$anchorX, $anchorZ] = $this->findClusterAnchor(SaplingType::JUNGLE);
				if($anchorX !== null && $anchorZ !== null){
					$x = $anchorX;
					$z = $anchorZ;
					$tree = new BigJungleTree();
				}
				break;

			case SaplingType::DARK_OAK:
				[$anchorX, $anchorZ] = $this->findClusterAnchor(SaplingType::DARK_OAK);
				if($anchorX === null || $anchorZ === null){
					return false;
				}

				$x = $anchorX;
				$z = $anchorZ;
				$tree = new DarkOakTree();
				break;
		}

		$tree ??= TreeFactory::get($random, $this->saplingType->getTreeType());
		$transaction = $tree?->getBlockTransaction($this->position->getWorld(), $x, $y, $z, $random);
		if($transaction === null){
			return false;
		}

		$ev = new StructureGrowEvent($this, $transaction, $player);
		$ev->call();
		if(!$ev->isCancelled()){
			return $transaction->apply();
		}
		return false;
	}

	/**
	 * @return array{0: int|null, 1: int|null}
	 */
	private function findClusterAnchor(SaplingType $saplingType) : array{
		$x = $this->position->getFloorX();
		$y = $this->position->getFloorY();
		$z = $this->position->getFloorZ();

		foreach([[0, 0], [-1, 0], [0, -1], [-1, -1]] as [$xOff, $zOff]){
			$anchorX = $x + $xOff;
			$anchorZ = $z + $zOff;
			if(
				$this->isSameSaplingTypeAt($anchorX, $y, $anchorZ, $saplingType) &&
				$this->isSameSaplingTypeAt($anchorX + 1, $y, $anchorZ, $saplingType) &&
				$this->isSameSaplingTypeAt($anchorX, $y, $anchorZ + 1, $saplingType) &&
				$this->isSameSaplingTypeAt($anchorX + 1, $y, $anchorZ + 1, $saplingType)
			){
				return [$anchorX, $anchorZ];
			}
		}

		return [null, null];
	}

	private function isSameSaplingTypeAt(int $x, int $y, int $z, SaplingType $saplingType) : bool{
		$block = $this->position->getWorld()->getBlockAt($x, $y, $z);
		return $block instanceof self && $block->saplingType === $saplingType;
	}

	public function getFuelTime() : int{
		return 100;
	}
}
