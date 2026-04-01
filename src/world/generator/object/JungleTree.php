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

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\CocoaBlock;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function intdiv;

class JungleTree extends Tree{
	private const MIN_TREE_HEIGHT = 4;
	private const MAX_TREE_HEIGHT_BOUND = 7;

	public function __construct(){
		parent::__construct(VanillaBlocks::JUNGLE_LOG(), VanillaBlocks::JUNGLE_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$height = $random->nextBoundedInt(self::MAX_TREE_HEIGHT_BOUND) + self::MIN_TREE_HEIGHT;
		$this->treeHeight = $height;

		if($y < $world->getMinY() || $y + $height + 1 >= $world->getMaxY()){
			return null;
		}

		if(!$this->canPlaceObjectForHeight($world, $height, $x, $y, $z)){
			return null;
		}

		$ground = $world->getBlockAt($x, $y - 1, $z)->getTypeId();
		if($ground !== BlockTypeIds::GRASS && $ground !== BlockTypeIds::DIRT && $ground !== BlockTypeIds::FARMLAND){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		for($yy = $y - 3 + $height; $yy <= $y + $height; ++$yy){
			$yOff = $yy - ($y + $height);
			$radius = 1 - intdiv($yOff, 2);

			for($xx = $x - $radius; $xx <= $x + $radius; ++$xx){
				$xOff = $xx - $x;
				for($zz = $z - $radius; $zz <= $z + $radius; ++$zz){
					$zOff = $zz - $z;
					if(abs($xOff) !== $radius || abs($zOff) !== $radius || ($random->nextBoundedInt(2) !== 0 && $yOff !== 0)){
						$block = $transaction->fetchBlockAt($xx, $yy, $zz);
						$typeId = $block->getTypeId();
						if($typeId === BlockTypeIds::AIR || $block instanceof Leaves || $typeId === BlockTypeIds::VINES){
							$transaction->addBlockAt($xx, $yy, $zz, $this->leafBlock);
						}
					}
				}
			}
		}

		for($yy = 0; $yy < $height; ++$yy){
			$currentY = $y + $yy;
			$trunkBlock = $transaction->fetchBlockAt($x, $currentY, $z);
			$trunkTypeId = $trunkBlock->getTypeId();
			if($trunkTypeId === BlockTypeIds::AIR || $trunkBlock instanceof Leaves || $trunkTypeId === BlockTypeIds::VINES){
				$transaction->addBlockAt($x, $currentY, $z, $this->trunkBlock);
				if($yy > 0){
					$this->placeVine($transaction, $random, $x - 1, $currentY, $z, Facing::EAST);
					$this->placeVine($transaction, $random, $x + 1, $currentY, $z, Facing::WEST);
					$this->placeVine($transaction, $random, $x, $currentY, $z - 1, Facing::SOUTH);
					$this->placeVine($transaction, $random, $x, $currentY, $z + 1, Facing::NORTH);
				}
			}
		}

		for($yy = $y - 3 + $height; $yy <= $y + $height; ++$yy){
			$yOff = $yy - ($y + $height);
			$radius = 2 - intdiv($yOff, 2);
			for($xx = $x - $radius; $xx <= $x + $radius; ++$xx){
				for($zz = $z - $radius; $zz <= $z + $radius; ++$zz){
					if($transaction->fetchBlockAt($xx, $yy, $zz) instanceof Leaves){
						if($random->nextBoundedInt(4) === 0 && $transaction->fetchBlockAt($xx - 1, $yy, $zz)->getTypeId() === BlockTypeIds::AIR){
							$this->placeHangingVine($transaction, $xx - 1, $yy, $zz, Facing::EAST);
						}
						if($random->nextBoundedInt(4) === 0 && $transaction->fetchBlockAt($xx + 1, $yy, $zz)->getTypeId() === BlockTypeIds::AIR){
							$this->placeHangingVine($transaction, $xx + 1, $yy, $zz, Facing::WEST);
						}
						if($random->nextBoundedInt(4) === 0 && $transaction->fetchBlockAt($xx, $yy, $zz - 1)->getTypeId() === BlockTypeIds::AIR){
							$this->placeHangingVine($transaction, $xx, $yy, $zz - 1, Facing::SOUTH);
						}
						if($random->nextBoundedInt(4) === 0 && $transaction->fetchBlockAt($xx, $yy, $zz + 1)->getTypeId() === BlockTypeIds::AIR){
							$this->placeHangingVine($transaction, $xx, $yy, $zz + 1, Facing::NORTH);
						}
					}
				}
			}
		}

		if($random->nextBoundedInt(5) === 0 && $height > 5){
			for($level = 0; $level < 2; ++$level){
				foreach(Facing::HORIZONTAL as $facing){
					if($random->nextBoundedInt(4 - $level) === 0){
						$opposite = Facing::opposite($facing);
						$this->placeCocoaPod(
							$transaction,
							$x + Facing::OFFSET[$opposite][0],
							$y + $height - 5 + $level,
							$z + Facing::OFFSET[$opposite][2],
							$facing,
							$random->nextBoundedInt(2)
						);
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

			for($xx = -$radius; $xx <= $radius; ++$xx){
				for($zz = -$radius; $zz <= $radius; ++$zz){
					if(!$this->canGrowInto($world->getBlockAt($x + $xx, $currentY, $z + $zz))){
						return false;
					}
				}
			}
		}

		return true;
	}

	private function placeVine(BlockTransaction $transaction, Random $random, int $x, int $y, int $z, int $face) : void{
		if($random->nextBoundedInt(3) > 0 && $transaction->fetchBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR){
			$transaction->addBlockAt($x, $y, $z, VanillaBlocks::VINES()->setFace($face, true));
		}
	}

	private function placeHangingVine(BlockTransaction $transaction, int $x, int $y, int $z, int $face) : void{
		for($depth = 0; $depth < 4; ++$depth){
			$currentY = $y - $depth;
			if($transaction->fetchBlockAt($x, $currentY, $z)->getTypeId() !== BlockTypeIds::AIR){
				break;
			}

			$transaction->addBlockAt($x, $currentY, $z, VanillaBlocks::VINES()->setFace($face, true));
		}
	}

	private function placeCocoaPod(BlockTransaction $transaction, int $x, int $y, int $z, int $facing, int $age) : void{
		if(!$this->canGrowInto($transaction->fetchBlockAt($x, $y, $z))){
			return;
		}

		$cocoa = VanillaBlocks::COCOA_POD()->setFacing($facing)->setAge($age);
		if($cocoa instanceof CocoaBlock){
			$transaction->addBlockAt($x, $y, $z, $cocoa);
		}
	}

	private function canGrowInto(Block $block) : bool{
		return $this->canOverride($block);
	}
}
