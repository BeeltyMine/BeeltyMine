<?php
/*
 *
 *  ____            _ _         __  __ _            
 * |  _ \          | | |       |  \/  (_)           
 * | |_) | ___  ___| | |_ _   _| \  / |_ _ __   ___ 
 * |  _ < / _ \/ _ \ | __| | | | |\/| | | '_ \ / _ \
 * | |_) |  __/  __/ | |_| |_| | |  | | | | | |  __/
 * |____/ \___|\___|_|\__|\__, |_|  |_|_|_| |_|\___|
 *                         __/ |                    
 *                        |___/                     
 *    _  _
 *   | )/ )
 *  \\ |//,' __
 * (")(_)-"()))=- BeeltyMine Team @ Since Ayrz
 *   (\\
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\tile\PistonArm as TilePistonArm;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\RedstonePowerHelper;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\event\block\BlockPistonEvent;
use pocketmine\event\entity\EntityMoveByPistonEvent;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\PistonExtendSound;
use pocketmine\world\sound\PistonRetractSound;

class Piston extends Opaque implements AnyFacing{
	use AnyFacingTrait;

	private const MAX_PUSH = 12;

	protected bool $extended = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->extended);
	}

	public function isExtended() : bool{
		return $this->extended;
	}

	/** @return $this */
	public function setExtended(bool $extended) : self{
		$this->extended = $extended;
		return $this;
	}

	protected function isSticky() : bool{
		return false;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$playerPos = $player->getPosition();
			if(
				abs($playerPos->x - $blockReplace->position->x) < 2 &&
				abs($playerPos->z - $blockReplace->position->z) < 2
			){
				$playerY = $playerPos->y + $player->getEyeHeight();
				if($playerY - $blockReplace->position->y > 2){
					$this->facing = Facing::UP;
				}elseif($blockReplace->position->y - $playerY > 0){
					$this->facing = Facing::DOWN;
				}else{
					$this->facing = Facing::opposite($player->getHorizontalFacing());
				}
			}else{
				$this->facing = Facing::opposite($player->getHorizontalFacing());
			}
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		if(!$world->getBlock($this->position) instanceof self){
			return;
		}

		$world->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function onPostPlace() : void{
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$this->syncArmTile();
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$this->removeHead();
		return parent::onBreak($item, $player, $returnedItems);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$current = $world->getBlock($this->position);
		if(!$current instanceof self){
			return;
		}

		$shouldExtend = $current->isReceivingPower();
		$headPos = $this->position->getSide($current->facing);
		$headBlock = $world->getBlock($headPos);
		$hasHead = $current->isOwnHead($headBlock);
		if($shouldExtend === $current->extended){
			if($current->extended){
				if(!$hasHead && $headBlock->canBeReplaced()){
					$world->setBlock($headPos, $current->createHeadBlock(), true);
				}
			}elseif($hasHead){
				$world->setBlock($headPos, VanillaBlocks::AIR(), true);
			}

			return;
		}

		$ev = new BlockPistonEvent($current, $current->facing, [], [], $shouldExtend);
		$ev->call();
		if($ev->isCancelled()){
			return;
		}

		if($shouldExtend){
			if($current->tryExtend()){
				$world->setBlock($this->position, (clone $current)->setExtended(true), true);
				$world->setBlock($this->position->getSide($current->facing), $current->createHeadBlock(), true);
				$world->addSound($this->position, new PistonExtendSound());
			}else{
				$world->scheduleDelayedBlockUpdate($this->position, 2);
			}
		}else{
			$current->removeHead();
			$current->tryRetract();
			$world->setBlock($this->position, (clone $current)->setExtended(false), true);
			$world->addSound($this->position, new PistonRetractSound());
		}
	}

	protected function createHeadBlock() : PistonArmCollision{
		$head = $this->isSticky() ? VanillaBlocks::STICKY_PISTON_ARM_COLLISION() : VanillaBlocks::PISTON_ARM_COLLISION();
		return $head->setFacing($this->facing);
	}

	protected function removeHead() : void{
		$world = $this->position->getWorld();
		$headPos = $this->position->getSide($this->facing);
		$head = $world->getBlock($headPos);
		if($this->isOwnHead($head)){
			$world->setBlock($headPos, VanillaBlocks::AIR(), true);
		}
	}

	private function syncArmTile() : void{
		$tile = $this->position->getWorld()->getTile($this->position);
		if($tile instanceof TilePistonArm){
			$tile->setVisualState($this->facing, $this->isSticky(), $this->isReceivingPower(), $this->extended);
		}
	}

	private function isReceivingPower() : bool{
		$maxPower = 0;
		foreach(Facing::OFFSET as $face => $_){
			if($face === $this->facing){
				continue;
			}

			$maxPower = max($maxPower, RedstonePowerHelper::getPowerFromFace($this, $face));
			if($maxPower >= 15){
				return true;
			}
		}

		return $maxPower > 0;
	}

	private function tryExtend() : bool{
		$moves = [];
		$visiting = [];
		if(!$this->collectBlocksToMove($this->position->getSide($this->facing), $this->facing, $moves, $visiting)){
			return false;
		}

		if(count($moves) === 0){
			return true;
		}

		$this->applyCollectedMoves($moves, $this->facing);
		return true;
	}

	private function tryRetract() : void{
		if(!$this->isSticky()){
			return;
		}

		$world = $this->position->getWorld();
		$pullToPos = $this->position->getSide($this->facing, 1);
		$pullFromPos = $this->position->getSide($this->facing, 2);
		if(
			!$world->isInWorld((int) $pullToPos->x, (int) $pullToPos->y, (int) $pullToPos->z) ||
			!$world->isInWorld((int) $pullFromPos->x, (int) $pullFromPos->y, (int) $pullFromPos->z)
		){
			return;
		}

		if(!$world->getBlock($pullToPos)->canBeReplaced()){
			return;
		}

		$moves = [];
		$visiting = [];
		$direction = Facing::opposite($this->facing);
		if(!$this->collectBlocksToMove($pullFromPos, $direction, $moves, $visiting)){
			return;
		}

		if(count($moves) === 0){
			return;
		}

		$this->applyCollectedMoves($moves, $direction);
	}

	/**
	 * @param array<string, array{0: Vector3, 1: Block}> $moves
	 * @param array<string, bool> $visiting
	 */
	private function collectBlocksToMove(Vector3 $position, int $direction, array &$moves, array &$visiting) : bool{
		$world = $this->position->getWorld();
		if(!$world->isInWorld((int) $position->x, (int) $position->y, (int) $position->z)){
			return false;
		}

		$key = $this->positionKey($position);
		if(isset($moves[$key])){
			return true;
		}
		if(isset($visiting[$key])){
			return false;
		}

		$block = $world->getBlock($position);
		if($block->canBeReplaced()){
			return true;
		}
		if(!$this->canMoveBlock($block, $position)){
			return false;
		}

		$visiting[$key] = true;

		$nextPos = $position->getSide($direction);
		if(!$this->collectBlocksToMove($nextPos, $direction, $moves, $visiting)){
			unset($visiting[$key]);
			return false;
		}

		if($this->isStickyBlock($block)){
			foreach(Facing::OFFSET as $side => $_){
				if($side === $direction || $side === Facing::opposite($direction)){
					continue;
				}

				$adjacentPos = $position->getSide($side);
				if(!$world->isInWorld((int) $adjacentPos->x, (int) $adjacentPos->y, (int) $adjacentPos->z)){
					continue;
				}

				$adjacentBlock = $world->getBlock($adjacentPos);
				if($adjacentBlock->canBeReplaced() || !$this->blocksStickTogether($block, $adjacentBlock)){
					continue;
				}

				if(!$this->collectBlocksToMove($adjacentPos, $direction, $moves, $visiting)){
					unset($visiting[$key]);
					return false;
				}
			}
		}

		if(count($moves) >= self::MAX_PUSH){
			unset($visiting[$key]);
			return false;
		}

		$moves[$key] = [$position, $block];
		unset($visiting[$key]);
		return true;
	}

	/**
	 * @param array<string, array{0: Vector3, 1: Block}> $moves
	 */
	private function applyCollectedMoves(array $moves, int $direction) : void{
		$world = $this->position->getWorld();
		$orderedMoves = array_values($moves);
		usort($orderedMoves, function(array $first, array $second) use ($direction) : int{
			$firstPriority = $this->movementPriority($first[0], $direction);
			$secondPriority = $this->movementPriority($second[0], $direction);

			return $secondPriority <=> $firstPriority;
		});
		$this->moveEntitiesForMoves($orderedMoves, $direction);

		foreach($orderedMoves as [$fromPos, $fromBlock]){
			$toPos = $fromPos->getSide($direction);
			$world->setBlock($toPos, clone $fromBlock, true);
			$world->setBlock($fromPos, VanillaBlocks::AIR(), true);
		}
	}

	/**
	 * @param list<array{0: Vector3, 1: Block}> $orderedMoves
	 */
	private function moveEntitiesForMoves(array $orderedMoves, int $direction) : void{
		$world = $this->position->getWorld();
		[$offsetX, $offsetY, $offsetZ] = Facing::OFFSET[$direction];
		if($offsetY < 0){
			return;
		}

		$movement = new Vector3((float) $offsetX, $offsetY > 0 ? 2.0 : 0.0, (float) $offsetZ);
		$expandedY = $offsetY === 0 ? 0.25 : 1.05;
		$movedEntityIds = [];

		foreach($orderedMoves as [$_, $fromBlock]){
			foreach($fromBlock->getCollisionBoxes() as $collisionBox){
				$queryBox = $collisionBox->expandedCopy(0.05, $expandedY, 0.05);
				foreach($world->getNearbyEntities($queryBox) as $entity){
					$entityId = $entity->getId();
					if(isset($movedEntityIds[$entityId])){
						continue;
					}

					if(!$this->shouldMoveEntityForCollisionBox($entity->getBoundingBox(), $collisionBox)){
						continue;
					}

					$event = new EntityMoveByPistonEvent($entity, $movement);
					$event->call();
					if($event->isCancelled()){
						continue;
					}

					$vector = $event->getVector();
					$entityPos = $entity->getPosition();
					if($entity->teleport($entityPos->add($vector->x, $vector->y, $vector->z))){
						$movedEntityIds[$entityId] = true;
					}
				}
			}
		}
	}

	private function shouldMoveEntityForCollisionBox(AxisAlignedBB $entityBox, AxisAlignedBB $blockBox) : bool{
		if($entityBox->intersectsWith($blockBox, 0.001)){
			return true;
		}

		return
			$entityBox->minY >= $blockBox->maxY - 0.001 &&
			$entityBox->minY <= $blockBox->maxY + 0.2 &&
			$entityBox->maxX > $blockBox->minX + 0.001 &&
			$entityBox->minX < $blockBox->maxX - 0.001 &&
			$entityBox->maxZ > $blockBox->minZ + 0.001 &&
			$entityBox->minZ < $blockBox->maxZ - 0.001;
	}

	private function movementPriority(Vector3 $position, int $direction) : int{
		[$offsetX, $offsetY, $offsetZ] = Facing::OFFSET[$direction];

		return (int) $position->x * $offsetX + (int) $position->y * $offsetY + (int) $position->z * $offsetZ;
	}

	private function isStickyBlock(Block $block) : bool{
		return $this->isSlimeBlock($block) || $this->isHoneyBlock($block);
	}

	private function blocksStickTogether(Block $first, Block $second) : bool{
		$firstSlime = $this->isSlimeBlock($first);
		$secondSlime = $this->isSlimeBlock($second);
		$firstHoney = $this->isHoneyBlock($first);
		$secondHoney = $this->isHoneyBlock($second);

		if(($firstSlime && $secondHoney) || ($firstHoney && $secondSlime)){
			return false;
		}

		return $firstSlime || $secondSlime || $firstHoney || $secondHoney;
	}

	private function isSlimeBlock(Block $block) : bool{
		return $block->getTypeId() === BlockTypeIds::SLIME;
	}

	private function isHoneyBlock(Block $block) : bool{
		return $block->getName() === "Honey Block";
	}

	private function isOwnHead(Block $head) : bool{
		return $head instanceof PistonArmCollision && $head->getFacing() === $this->facing;
	}

	private function positionKey(Vector3 $position) : string{
		return (int) $position->x . ':' . (int) $position->y . ':' . (int) $position->z;
	}

	private function canMoveBlock(Block $block, Vector3 $position) : bool{
		if($block->getBreakInfo()->getHardness() < 0){
			return false;
		}

		if($block instanceof PistonArmCollision){
			return false;
		}

		$world = $this->position->getWorld();
		if($world->getTile($position) !== null){
			return false;
		}

		if(match($block->getTypeId()){
			BlockTypeIds::BEDROCK,
			BlockTypeIds::OBSIDIAN,
			BlockTypeIds::CRYING_OBSIDIAN,
			BlockTypeIds::RESPAWN_ANCHOR,
			BlockTypeIds::REINFORCED_DEEPSLATE,
			BlockTypeIds::END_PORTAL_FRAME => true,
			default => false
		}){
			return false;
		}

		if($block instanceof self && $block->isExtended()){
			return false;
		}

		return true;
	}
}
