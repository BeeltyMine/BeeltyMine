<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\MangrovePropagule;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\World;
use function abs;
use function cos;
use function count;
use function max;
use function min;
use function round;
use function sin;
use function sqrt;

final class MangroveTree extends Tree{
	private const GOLDEN_ANGLE = 2.39996;

	private bool $withBeeNest = false;
	private int $minY = World::Y_MIN;
	private int $maxY = World::Y_MAX;

	public function __construct(){
		parent::__construct(VanillaBlocks::MANGROVE_LOG(), VanillaBlocks::MANGROVE_LEAVES(), 0);
	}

	/** @return $this */
	public function setWithBeeNest(bool $withBeeNest) : self{
		$this->withBeeNest = $withBeeNest;
		return $this;
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->minY = $world->getMinY();
		$this->maxY = $world->getMaxY();

		$trunkHeight = 4 + $random->nextBoundedInt(4);
		$trunkBase = 2 + $random->nextBoundedInt(5);
		$topY = $y + $trunkBase + $trunkHeight + 2;
		if($y < $this->minY + 1 || $topY >= $this->maxY){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$leafBlock = (clone VanillaBlocks::MANGROVE_LEAVES())->setNoDecay(true)->setCheckDecay(false);

		for($i = 0; $i < $trunkHeight; ++$i){
			$this->setBlockIfInBounds($transaction, $x, $y + $trunkBase + $i, $z, $this->trunkBlock);
		}

		$roots = 3 + $random->nextBoundedInt(2);
		$rootMaxLength = 30.0;
		$rootDroop = 0.06;
		$rootVerticalDirection = -0.4 - $random->nextFloat() * 0.3;
		$rootAngle = $random->nextFloat() * 2.0 * \M_PI;

		for($r = 0; $r < $roots; ++$r){
			$dx = sin($rootAngle);
			$dy = $rootVerticalDirection;
			$dz = cos($rootAngle);
			$mag = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
			if($mag <= 0.0){
				$rootAngle += self::GOLDEN_ANGLE;
				continue;
			}
			$dx /= $mag;
			$dy /= $mag;
			$dz /= $mag;

			for($i = 0.0; $i <= $rootMaxLength; $i += 0.5){
				$cx = (int) round($x + $i * $dx);
				$cy = (int) round($y + $trunkBase + $i * $dy);
				$cz = (int) round($z + $i * $dz);

				if(!$this->isInVerticalBounds($cy)){
					break;
				}

				$currentBlock = $transaction->fetchBlockAt($cx, $cy, $cz);
				$typeId = $currentBlock->getTypeId();

				if($typeId === BlockTypeIds::STONE){
					break;
				}

				if($typeId === BlockTypeIds::MUD){
					$transaction->addBlockAt($cx, $cy, $cz, VanillaBlocks::MUDDY_MANGROVE_ROOTS());
				}else{
					$transaction->addBlockAt($cx, $cy, $cz, VanillaBlocks::MANGROVE_ROOTS());
				}
				$this->maybePlaceMossCarpet($transaction, $cx, $cy, $cz, $random);

				$dy -= $rootDroop;
				$mag = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
				if($mag <= 0.0){
					break;
				}
				$dx /= $mag;
				$dy /= $mag;
				$dz /= $mag;
			}

			$rootAngle += self::GOLDEN_ANGLE;
		}

		$sideBranchInterval = 3 + $random->nextBoundedInt(2);
		$sideBranchMinHeight = 2 + $random->nextBoundedInt(3);
		$sideBranchLengthMin = 3;
		$sideBranchLengthVariation = 2;

		$branchAngle = $random->nextFloat() * 2.0 * \M_PI;
		for($i = 0; $i < $trunkHeight; ++$i){
			if($i > $sideBranchMinHeight && $i % $sideBranchInterval === 0){
				$dx = sin($branchAngle);
				$dy = 0.5;
				$dz = cos($branchAngle);
				$mag = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
				if($mag <= 0.0){
					$branchAngle += self::GOLDEN_ANGLE;
					continue;
				}
				$dx /= $mag;
				$dy /= $mag;
				$dz /= $mag;

				$originY = $y + $trunkBase + $i;
				$branchLength = $sideBranchLengthMin + $random->nextBoundedInt($sideBranchLengthVariation + 1);
				for($l = 1; $l <= $branchLength; ++$l){
					$bx = (int) round($x + $l * $dx);
					$by = (int) round($originY + $l * $dy);
					$bz = (int) round($z + $l * $dz);
					$this->setBlockIfInBounds($transaction, $bx, $by, $bz, $this->trunkBlock);
				}

				$branchAngle += self::GOLDEN_ANGLE;
			}
		}

		$topBranches = 10 + $random->nextBoundedInt(3);
		$branchAngle = $random->nextFloat() * 2.0 * \M_PI;
		for($b = 1; $b <= $topBranches; ++$b){
			$t = $b / $topBranches;
			$ti = 1.0 - $t;

			$dx = sin($branchAngle) * $t;
			$dy = 0.4;
			$dz = cos($branchAngle) * $t;
			$mag = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
			if($mag <= 0.0){
				$branchAngle += self::GOLDEN_ANGLE;
				continue;
			}
			$dx /= $mag;
			$dy /= $mag;
			$dz /= $mag;

			$originY = $y + $trunkBase + $trunkHeight;
			$branchLength = (int) (7.0 * $ti + 4.0 * $t);
			for($l = 0; $l <= $branchLength; ++$l){
				$bx = (int) round($x + $l * $dx);
				$by = (int) round($originY + $l * $dy);
				$bz = (int) round($z + $l * $dz);

				$this->placeLeafCluster($transaction, $bx, $by, $bz, $random, $leafBlock);
				if($l < $branchLength){
					$this->setBlockIfInBounds($transaction, $bx, $by, $bz, $this->trunkBlock);
				}

				if($this->withBeeNest){
					$this->withBeeNest = false;
					$face = Facing::HORIZONTAL[$random->nextRange(0, count(Facing::HORIZONTAL) - 1)];
					$targetX = $bx + Facing::OFFSET[$face][0];
					$targetY = $by - 1;
					$targetZ = $bz + Facing::OFFSET[$face][2];
					if($this->isInVerticalBounds($targetY)){
						$transaction->addBlockAt(
							$targetX,
							$targetY,
							$targetZ,
							VanillaBlocks::BEE_NEST()
								->setFacing(Facing::opposite($face))
								->setHoneyLevel($random->nextRange(0, 4))
						);
					}
				}
			}

			$branchAngle += self::GOLDEN_ANGLE;
		}

		return $transaction;
	}

	private function placeLeafCluster(BlockTransaction $transaction, int $x, int $y, int $z, Random $random, Leaves $leafBlock) : void{
		if(!$this->isInVerticalBounds($y)){
			return;
		}
		if($this->canPlaceLeafAt($transaction, $x, $y, $z)){
			$transaction->addBlockAt($x, $y, $z, clone $leafBlock);
		}

		if($random->nextBoundedInt(15) === 0 && $this->isInVerticalBounds($y - 1) && $this->isAirAt($transaction, $x, $y - 1, $z)){
			$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::MANGROVE_PROPAGULE()->setHanging(true)->setStage(4));
		}

		if($random->nextBoundedInt(10) < 8){
			$offsets = [
				[0, 0, 1], [0, 0, -1], [1, 0, 0], [-1, 0, 0],
				[1, 0, 1], [1, 0, -1], [-1, 0, 1], [-1, 0, -1],
				[0, 1, 0], [1, 1, 0], [-1, 1, 0], [0, 1, 1], [0, 1, -1]
			];

			foreach($offsets as [$offX, $offY, $offZ]){
				$xx = $x + $offX;
				$yy = $y + $offY;
				$zz = $z + $offZ;
				if($this->canPlaceLeafAt($transaction, $xx, $yy, $zz)){
					$transaction->addBlockAt($xx, $yy, $zz, clone $leafBlock);
				}
			}
		}

		foreach(Facing::HORIZONTAL as $face){
			if($random->nextBoundedInt(5) === 0){
				$vineX = $x + Facing::OFFSET[$face][0];
				$vineZ = $z + Facing::OFFSET[$face][2];
				if($this->isAirAt($transaction, $vineX, $y, $vineZ)){
					$this->addVine($transaction, $vineX, $y, $vineZ, $face);
					$this->addHangingVine($transaction, $vineX, $y, $vineZ, $face, $random);
				}
			}
		}
	}

	private function maybePlaceMossCarpet(BlockTransaction $transaction, int $x, int $y, int $z, Random $random) : void{
		if($random->nextBoundedInt(6) === 0){
			$aboveY = $y + 1;
			if($this->isInVerticalBounds($aboveY) && $this->isAirAt($transaction, $x, $aboveY, $z)){
				$transaction->addBlockAt($x, $aboveY, $z, VanillaBlocks::MOSS_CARPET());
			}
		}
	}

	private function addVine(BlockTransaction $transaction, int $x, int $y, int $z, int $face) : void{
		if(!$this->isInVerticalBounds($y)){
			return;
		}
		$transaction->addBlockAt($x, $y, $z, VanillaBlocks::VINES()->setFace($face, true));
	}

	private function addHangingVine(BlockTransaction $transaction, int $x, int $y, int $z, int $face, Random $random) : void{
		$length = 1 + $random->nextBoundedInt(3);
		for($i = 1; $i <= $length; ++$i){
			$yy = $y - $i;
			if(!$this->isInVerticalBounds($yy) || !$this->isAirAt($transaction, $x, $yy, $z)){
				break;
			}
			$this->addVine($transaction, $x, $yy, $z, $face);
		}
	}

	private function isAirAt(BlockTransaction $transaction, int $x, int $y, int $z) : bool{
		return $transaction->fetchBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR;
	}

	private function canPlaceLeafAt(BlockTransaction $transaction, int $x, int $y, int $z) : bool{
		if(!$this->isInVerticalBounds($y)){
			return false;
		}

		$block = $transaction->fetchBlockAt($x, $y, $z);
		$typeId = $block->getTypeId();
		if($typeId === BlockTypeIds::MANGROVE_LOG || $typeId === BlockTypeIds::MANGROVE_ROOTS || $typeId === BlockTypeIds::MUDDY_MANGROVE_ROOTS){
			return false;
		}

		return !$block->isSolid();
	}

	private function setBlockIfInBounds(BlockTransaction $transaction, int $x, int $y, int $z, Block $block) : void{
		if($this->isInVerticalBounds($y)){
			$transaction->addBlockAt($x, $y, $z, $block);
		}
	}

	private function isInVerticalBounds(int $y) : bool{
		return $y >= $this->minY && $y < $this->maxY;
	}

	protected function canOverride(Block $block) : bool{
		return parent::canOverride($block)
			|| $block instanceof MangrovePropagule
			|| $block->getTypeId() === BlockTypeIds::MANGROVE_PROPAGULE;
	}
}
