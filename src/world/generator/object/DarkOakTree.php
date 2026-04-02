<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeTags;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function count;

final class DarkOakTree extends Tree{
	public function __construct(){
		parent::__construct(VanillaBlocks::DARK_OAK_LOG(), VanillaBlocks::DARK_OAK_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$height = $random->nextBoundedInt(3) + $random->nextBoundedInt(2) + 6;

		if($y < $world->getMinY() + 1 || $y + $height + 1 >= $world->getMaxY()){
			return null;
		}

		if(
			!$this->isValidGround($world->getBlockAt($x, $y - 1, $z)) ||
			!$this->isValidGround($world->getBlockAt($x + 1, $y - 1, $z)) ||
			!$this->isValidGround($world->getBlockAt($x, $y - 1, $z + 1)) ||
			!$this->isValidGround($world->getBlockAt($x + 1, $y - 1, $z + 1))
		){
			return null;
		}

		if(!$this->canPlaceObjectForHeight($world, $height, $x, $y, $z)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$this->setDirtAt($transaction, $x, $y - 1, $z);
		$this->setDirtAt($transaction, $x + 1, $y - 1, $z);
		$this->setDirtAt($transaction, $x, $y - 1, $z + 1);
		$this->setDirtAt($transaction, $x + 1, $y - 1, $z + 1);

		$direction = Facing::HORIZONTAL[$random->nextRange(0, count(Facing::HORIZONTAL) - 1)];
		$bendStart = $height - $random->nextBoundedInt(4);
		$bendSteps = 2 - $random->nextBoundedInt(3);
		$trunkX = $x;
		$trunkZ = $z;
		$topY = $y + $height - 1;

		for($offsetY = 0; $offsetY < $height; ++$offsetY){
			if($offsetY >= $bendStart && $bendSteps > 0){
				$trunkX += Facing::OFFSET[$direction][0];
				$trunkZ += Facing::OFFSET[$direction][2];
				--$bendSteps;
			}

			$currentY = $y + $offsetY;
			if($this->canGrowInto($transaction->fetchBlockAt($trunkX, $currentY, $trunkZ))){
				$this->placeLogAt($transaction, $trunkX, $currentY, $trunkZ);
				$this->placeLogAt($transaction, $trunkX + 1, $currentY, $trunkZ);
				$this->placeLogAt($transaction, $trunkX, $currentY, $trunkZ + 1);
				$this->placeLogAt($transaction, $trunkX + 1, $currentY, $trunkZ + 1);
			}
		}

		for($offX = -2; $offX <= 0; ++$offX){
			for($offZ = -2; $offZ <= 0; ++$offZ){
				$this->placeLeafAt($transaction, $trunkX + $offX, $topY - 1, $trunkZ + $offZ);
				$this->placeLeafAt($transaction, 1 + $trunkX - $offX, $topY - 1, $trunkZ + $offZ);
				$this->placeLeafAt($transaction, $trunkX + $offX, $topY - 1, 1 + $trunkZ - $offZ);
				$this->placeLeafAt($transaction, 1 + $trunkX - $offX, $topY - 1, 1 + $trunkZ - $offZ);

				if(($offX > -2 || $offZ > -1) && ($offX !== -1 || $offZ !== -2)){
					$this->placeLeafAt($transaction, $trunkX + $offX, $topY + 1, $trunkZ + $offZ);
					$this->placeLeafAt($transaction, 1 + $trunkX - $offX, $topY + 1, $trunkZ + $offZ);
					$this->placeLeafAt($transaction, $trunkX + $offX, $topY + 1, 1 + $trunkZ - $offZ);
					$this->placeLeafAt($transaction, 1 + $trunkX - $offX, $topY + 1, 1 + $trunkZ - $offZ);
				}
			}
		}

		if($random->nextBoolean()){
			$this->placeLeafAt($transaction, $trunkX, $topY + 2, $trunkZ);
			$this->placeLeafAt($transaction, $trunkX + 1, $topY + 2, $trunkZ);
			$this->placeLeafAt($transaction, $trunkX + 1, $topY + 2, $trunkZ + 1);
			$this->placeLeafAt($transaction, $trunkX, $topY + 2, $trunkZ + 1);
		}

		for($offX = -3; $offX <= 4; ++$offX){
			for($offZ = -3; $offZ <= 4; ++$offZ){
				if(
					($offX !== -3 || $offZ !== -3) &&
					($offX !== -3 || $offZ !== 4) &&
					($offX !== 4 || $offZ !== -3) &&
					($offX !== 4 || $offZ !== 4) &&
					(abs($offX) < 3 || abs($offZ) < 3)
				){
					$this->placeLeafAt($transaction, $trunkX + $offX, $topY, $trunkZ + $offZ);
				}
			}
		}

		for($offX = -1; $offX <= 2; ++$offX){
			for($offZ = -1; $offZ <= 2; ++$offZ){
				if(($offX < 0 || $offX > 1 || $offZ < 0 || $offZ > 1) && $random->nextBoundedInt(3) === 0){
					$depth = $random->nextBoundedInt(3) + 2;

					for($step = 0; $step < $depth; ++$step){
						$this->placeLogAt($transaction, $x + $offX, $topY - $step - 1, $z + $offZ);
					}

					for($ringX = -1; $ringX <= 1; ++$ringX){
						for($ringZ = -1; $ringZ <= 1; ++$ringZ){
							$this->placeLeafAt($transaction, $trunkX + $offX + $ringX, $topY, $trunkZ + $offZ + $ringZ);
						}
					}

					for($ringX = -2; $ringX <= 2; ++$ringX){
						for($ringZ = -2; $ringZ <= 2; ++$ringZ){
							if(abs($ringX) !== 2 || abs($ringZ) !== 2){
								$this->placeLeafAt($transaction, $trunkX + $offX + $ringX, $topY - 1, $trunkZ + $offZ + $ringZ);
							}
						}
					}
				}
			}
		}

		return $transaction;
	}

	private function canPlaceObjectForHeight(ChunkManager $world, int $height, int $x, int $y, int $z) : bool{
		for($yy = 0; $yy <= $height + 1; ++$yy){
			$radius = 1;
			if($yy === 0){
				$radius = 0;
			}elseif($yy >= $height - 1){
				$radius = 2;
			}

			$currentY = $y + $yy;
			if($currentY < $world->getMinY() || $currentY >= $world->getMaxY()){
				return false;
			}

			for($offX = -$radius; $offX <= $radius; ++$offX){
				for($offZ = -$radius; $offZ <= $radius; ++$offZ){
					if(!$this->canGrowInto($world->getBlockAt($x + $offX, $currentY, $z + $offZ))){
						return false;
					}
				}
			}
		}

		return true;
	}

	private function placeLogAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		if($this->canGrowInto($transaction->fetchBlockAt($x, $y, $z))){
			$transaction->addBlockAt($x, $y, $z, $this->trunkBlock);
		}
	}

	private function placeLeafAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		if($transaction->fetchBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR){
			$transaction->addBlockAt($x, $y, $z, $this->leafBlock);
		}
	}

	private function setDirtAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		$transaction->addBlockAt($x, $y, $z, VanillaBlocks::DIRT());
	}

	private function isValidGround(Block $block) : bool{
		return $block->hasTypeTag(BlockTypeTags::DIRT) ||
			$block->hasTypeTag(BlockTypeTags::MUD) ||
			$block->hasTypeTag(BlockTypeTags::MOSS);
	}

	private function canGrowInto(Block $block) : bool{
		return $this->canOverride($block);
	}
}