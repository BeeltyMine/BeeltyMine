<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\utils\PillarRotation;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function max;

final class CherryTree extends Tree{
	private const LEAVES_RADIUS = 4;

	private Block $logYAxis;
	private Block $logXAxis;
	private Block $logZAxis;

	public function __construct(){
		parent::__construct(VanillaBlocks::CHERRY_LOG(), VanillaBlocks::CHERRY_LEAVES(), 0);

		$this->logYAxis = clone $this->trunkBlock;
		$this->logXAxis = clone $this->trunkBlock;
		$this->logZAxis = clone $this->trunkBlock;
		$this->setLogAxis($this->logYAxis, Axis::Y);
		$this->setLogAxis($this->logXAxis, Axis::X);
		$this->setLogAxis($this->logZAxis, Axis::Z);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		if($random->nextBoolean()){
			$bigTree = $this->generateBigTree($world, $random, $x, $y, $z);
			if($bigTree !== null){
				return $bigTree;
			}
		}

		return $this->generateSmallTree($world, $random, $x, $y, $z);
	}

	private function generateBigTree(ChunkManager $world, Random $random, int $x, int $y, int $z) : ?BlockTransaction{
		$mainTrunkHeight = ($random->nextBoolean() ? 1 : 0) + 10;
		if(!$this->canPlaceObjectForHeight($world, $mainTrunkHeight, $x, $y, $z)){
			return null;
		}

		$growOnXAxis = $random->nextBoolean();
		$xMultiplier = $growOnXAxis ? 1 : 0;
		$zMultiplier = $growOnXAxis ? 0 : 1;

		$leftTrunkLength = $random->nextRange(2, 3);
		$leftTrunkHeight = $random->nextRange(3, 4);
		$leftTrunkStartY = $random->nextRange(4, 5);

		if(!$this->canPlaceObjectForHeight($world, $leftTrunkHeight, $x - $leftTrunkLength * $xMultiplier, $y + $leftTrunkStartY, $z - $leftTrunkLength * $zMultiplier)){
			$growOnXAxis = !$growOnXAxis;
			$xMultiplier = $growOnXAxis ? 1 : 0;
			$zMultiplier = $growOnXAxis ? 0 : 1;
			if(!$this->canPlaceObjectForHeight($world, $leftTrunkHeight, $x - $leftTrunkLength * $xMultiplier, $y + $leftTrunkStartY, $z - $leftTrunkLength * $zMultiplier)){
				return null;
			}
		}

		$rightTrunkLength = $random->nextRange(2, 3);
		$rightTrunkHeight = $random->nextRange(3, 4);
		$rightTrunkStartY = $random->nextRange(4, 5);
		if(!$this->canPlaceObjectForHeight($world, $rightTrunkHeight, $x + $rightTrunkLength * $xMultiplier, $y + $rightTrunkStartY, $z + $rightTrunkLength * $zMultiplier)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		for($yy = 0; $yy < $mainTrunkHeight; ++$yy){
			$transaction->addBlockAt($x, $y + $yy, $z, clone $this->logYAxis);
		}

		$sideBlock = $growOnXAxis ? $this->logXAxis : $this->logZAxis;

		for($xx = 1; $xx <= $leftTrunkLength; ++$xx){
			$leftX = $x - $xx * $xMultiplier;
			$leftY = $y + $leftTrunkStartY;
			$leftZ = $z - $xx * $zMultiplier;
			if($this->canGrowInto($transaction->fetchBlockAt($leftX, $leftY, $leftZ))){
				$transaction->addBlockAt($leftX, $leftY, $leftZ, clone $sideBlock);
			}
		}

		for($yy = 1; $yy < $leftTrunkHeight; ++$yy){
			$leftX = $x - $leftTrunkLength * $xMultiplier;
			$leftY = $y + $leftTrunkStartY + $yy;
			$leftZ = $z - $leftTrunkLength * $zMultiplier;
			if($this->canGrowInto($transaction->fetchBlockAt($leftX, $leftY, $leftZ))){
				$transaction->addBlockAt($leftX, $leftY, $leftZ, clone $this->logYAxis);
			}
		}

		if($leftTrunkStartY === 4){
			$tmpX = $x - $leftTrunkLength * $xMultiplier;
			$tmpY = $y + $leftTrunkStartY;
			$tmpZ = $z - $leftTrunkLength * $zMultiplier;
			$transaction->addBlockAt($tmpX, $tmpY, $tmpZ, VanillaBlocks::AIR());
			$tmpX += $xMultiplier;
			$tmpY += 1;
			$tmpZ += $zMultiplier;
			if($this->canGrowInto($transaction->fetchBlockAt($tmpX, $tmpY, $tmpZ))){
				$transaction->addBlockAt($tmpX, $tmpY, $tmpZ, clone $this->logYAxis);
			}
			$tmpX -= $xMultiplier;
			$tmpZ -= $zMultiplier;
			if($this->canGrowInto($transaction->fetchBlockAt($tmpX, $tmpY, $tmpZ))){
				$transaction->addBlockAt($tmpX, $tmpY, $tmpZ, clone $sideBlock);
			}
		}

		for($xx = 1; $xx <= $rightTrunkLength; ++$xx){
			$rightX = $x + $xx * $xMultiplier;
			$rightY = $y + $rightTrunkStartY;
			$rightZ = $z + $xx * $zMultiplier;
			if($this->canGrowInto($transaction->fetchBlockAt($rightX, $rightY, $rightZ))){
				$transaction->addBlockAt($rightX, $rightY, $rightZ, clone $sideBlock);
			}
		}

		for($yy = 1; $yy < $rightTrunkHeight; ++$yy){
			$rightX = $x + $rightTrunkLength * $xMultiplier;
			$rightY = $y + $rightTrunkStartY + $yy;
			$rightZ = $z + $rightTrunkLength * $zMultiplier;
			if($this->canGrowInto($transaction->fetchBlockAt($rightX, $rightY, $rightZ))){
				$transaction->addBlockAt($rightX, $rightY, $rightZ, clone $this->logYAxis);
			}
		}

		if($rightTrunkStartY === 4){
			$tmpX = $x + $rightTrunkLength * $xMultiplier;
			$tmpY = $y + $rightTrunkStartY;
			$tmpZ = $z + $rightTrunkLength * $zMultiplier;
			$transaction->addBlockAt($tmpX, $tmpY, $tmpZ, VanillaBlocks::AIR());
			$tmpX -= $xMultiplier;
			$tmpY += 1;
			$tmpZ -= $zMultiplier;
			if($this->canGrowInto($transaction->fetchBlockAt($tmpX, $tmpY, $tmpZ))){
				$transaction->addBlockAt($tmpX, $tmpY, $tmpZ, clone $this->logYAxis);
			}
			$tmpX += $xMultiplier;
			$tmpZ += $zMultiplier;
			if($this->canGrowInto($transaction->fetchBlockAt($tmpX, $tmpY, $tmpZ))){
				$transaction->addBlockAt($tmpX, $tmpY, $tmpZ, clone $sideBlock);
			}
		}

		$this->generateLeaves($transaction, $random, $x, $y + $mainTrunkHeight + 1, $z);
		$this->generateLeaves($transaction, $random, $x - $leftTrunkLength * $xMultiplier, $y + $leftTrunkStartY + $leftTrunkHeight + 1, $z - $leftTrunkLength * $zMultiplier);
		$this->generateLeaves($transaction, $random, $x + $rightTrunkLength * $xMultiplier, $y + $rightTrunkStartY + $rightTrunkHeight + 1, $z + $rightTrunkLength * $zMultiplier);

		return $transaction;
	}

	private function generateSmallTree(ChunkManager $world, Random $random, int $x, int $y, int $z) : ?BlockTransaction{
		$mainTrunkHeight = ($random->nextBoolean() ? 1 : 0) + 4;
		$sideTrunkHeight = $random->nextRange(3, 4);
		if(!$this->canPlaceObjectForHeight($world, $mainTrunkHeight + 1, $x, $y, $z)){
			return null;
		}

		$growDirection = $random->nextRange(0, 3);
		$xMultiplier = 0;
		$zMultiplier = 0;
		$canPlace = false;

		for($i = 0; $i < 4; ++$i){
			$growDirection = ($growDirection + 1) % 4;
			$xMultiplier = match($growDirection){
				0 => -1,
				1 => 1,
				default => 0,
			};
			$zMultiplier = match($growDirection){
				2 => -1,
				3 => 1,
				default => 0,
			};

			if($this->canPlaceObjectForHeight($world, $sideTrunkHeight, $x + $xMultiplier * $sideTrunkHeight, $y, $z + $zMultiplier * $sideTrunkHeight)){
				$canPlace = true;
				break;
			}
		}

		if(!$canPlace){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		for($yy = 0; $yy < $mainTrunkHeight; ++$yy){
			if($this->canGrowInto($transaction->fetchBlockAt($x, $y + $yy, $z))){
				$transaction->addBlockAt($x, $y + $yy, $z, clone $this->logYAxis);
			}
		}

		$sideBlock = $xMultiplier === 0 ? $this->logZAxis : $this->logXAxis;
		for($yy = 1; $yy <= $sideTrunkHeight; ++$yy){
			$tmpX = $x + $yy * $xMultiplier;
			$tmpY = $y + $mainTrunkHeight + $yy - 2;
			$tmpZ = $z + $yy * $zMultiplier;

			if($this->canGrowInto($transaction->fetchBlockAt($tmpX, $tmpY, $tmpZ))){
				$transaction->addBlockAt($tmpX, $tmpY, $tmpZ, clone $sideBlock);
			}

			if($yy === $sideTrunkHeight - 1 && $sideTrunkHeight > 3){
				continue;
			}

			$tmpY += 1;
			if($this->canGrowInto($transaction->fetchBlockAt($tmpX, $tmpY, $tmpZ))){
				$transaction->addBlockAt($tmpX, $tmpY, $tmpZ, clone $this->logYAxis);
			}
		}

		$this->generateLeaves($transaction, $random, $x + $sideTrunkHeight * $xMultiplier, $y + $mainTrunkHeight + $sideTrunkHeight, $z + $sideTrunkHeight * $zMultiplier);
		return $transaction;
	}

	private function generateLeaves(BlockTransaction $transaction, Random $random, int $x, int $y, int $z) : void{
		for($dy = -2; $dy <= 2; ++$dy){
			for($dx = -self::LEAVES_RADIUS; $dx <= self::LEAVES_RADIUS; ++$dx){
				for($dz = -self::LEAVES_RADIUS; $dz <= self::LEAVES_RADIUS; ++$dz){
					$currentRadius = self::LEAVES_RADIUS - max(1, abs($dy));
					if($dx * $dx + $dz * $dz > $currentRadius * $currentRadius){
						continue;
					}

					$this->placeCherryLeafIfPossible($transaction, $x + $dx, $y + $dy, $z + $dz);

					if($dy === -2 && $random->nextRange(0, 2) === 0){
						$this->placeCherryLeafIfPossible($transaction, $x + $dx, $y + $dy - 1, $z + $dz);
					}
				}
			}
		}
	}

	private function placeCherryLeafIfPossible(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		$block = $transaction->fetchBlockAt($x, $y, $z);
		$typeId = $block->getTypeId();
		if($typeId !== BlockTypeIds::AIR && !($block instanceof Leaves) && $typeId !== BlockTypeIds::FLOWERING_AZALEA_LEAVES){
			return;
		}

		$leaf = clone $this->leafBlock;
		if($leaf instanceof Leaves){
			$leaf->setNoDecay(true)->setCheckDecay(false);
		}
		$transaction->addBlockAt($x, $y, $z, $leaf);
	}

	private function canPlaceObjectForHeight(ChunkManager $world, int $treeHeight, int $x, int $y, int $z) : bool{
		$radiusToCheck = 0;
		for($yy = 0; $yy < $treeHeight + 3; ++$yy){
			if($yy === 1 || $yy === $treeHeight){
				++$radiusToCheck;
			}

			$currentY = $y + $yy;
			if($currentY < $world->getMinY() || $currentY >= $world->getMaxY()){
				return false;
			}

			for($xx = -$radiusToCheck; $xx <= $radiusToCheck; ++$xx){
				for($zz = -$radiusToCheck; $zz <= $radiusToCheck; ++$zz){
					if(!$this->canGrowInto($world->getBlockAt($x + $xx, $currentY, $z + $zz))){
						return false;
					}
				}
			}
		}

		return true;
	}

	private function canGrowInto(Block $block) : bool{
		return $this->canOverride($block) || $block->getTypeId() === BlockTypeIds::AIR;
	}

	private function setLogAxis(Block $block, int $axis) : void{
		if($block instanceof PillarRotation){
			$block->setAxis($axis);
		}
	}
}
