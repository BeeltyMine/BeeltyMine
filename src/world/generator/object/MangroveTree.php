<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function count;

final class MangroveTree extends Tree{
	/** @var Vector3[] */
	private array $canopyCenters = [];

	public function __construct(){
		parent::__construct(VanillaBlocks::MANGROVE_LOG(), VanillaBlocks::MANGROVE_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $random->nextBoundedInt(3) + 7;
		$this->canopyCenters = [];
		return parent::getBlockTransaction($world, $x, $y, $z, $random);
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		$directionIndex = $random->nextRange(0, count(Facing::HORIZONTAL) - 1);
		$direction = Facing::HORIZONTAL[$directionIndex];
		$cx = $x;
		$cy = $y;
		$cz = $z;
		$bendStart = max(2, $trunkHeight - 4);
		$bendLength = 1 + $random->nextBoundedInt(2);

		for($i = 0; $i < $trunkHeight; ++$i){
			$transaction->addBlockAt($cx, $cy, $cz, $this->trunkBlock);
			if($i >= $bendStart && $i < $bendStart + $bendLength){
				$cx += Facing::OFFSET[$direction][0];
				$cz += Facing::OFFSET[$direction][2];
			}
			$cy++;
		}

		$this->canopyCenters[] = new Vector3($cx, $cy - 1, $cz);
		$sideDirection = Facing::HORIZONTAL[($directionIndex + 1 + $random->nextBoundedInt(2)) % count(Facing::HORIZONTAL)];
		$this->extendBranch($transaction, $cx, $cy - 2, $cz, $direction, 2);
		$this->extendBranch($transaction, $cx, $cy - 3, $cz, $sideDirection, 1);
		$this->placeRoots($transaction, $x, $y, $z, $directionIndex);
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		foreach($this->canopyCenters as $index => $center){
			$radius = $index === 0 ? 3 : 2;
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() + 1, $center->getFloorZ(), 1);
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY(), $center->getFloorZ(), $radius, trimCorners: $index !== 0);
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() - 1, $center->getFloorZ(), $radius, trimCorners: true, hanging: true, propaguleChance: $index === 0 ? 5 : 4);
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() - 2, $center->getFloorZ(), $radius - 1, trimCorners: true, hanging: true, propaguleChance: 0);
		}
	}

	private function extendBranch(BlockTransaction $transaction, int $x, int $y, int $z, int $direction, int $length) : void{
		$branchX = $x;
		$branchY = $y;
		$branchZ = $z;

		for($step = 0; $step < $length; ++$step){
			$branchX += Facing::OFFSET[$direction][0];
			$branchZ += Facing::OFFSET[$direction][2];
			if($step > 0){
				++$branchY;
			}
			$transaction->addBlockAt($branchX, $branchY, $branchZ, $this->trunkBlock);
		}

		$this->canopyCenters[] = new Vector3($branchX, $branchY, $branchZ);
	}

	private function placeRoots(BlockTransaction $transaction, int $x, int $y, int $z, int $directionIndex) : void{
		$rootOffsets = [
			[$x + 1, $z, 1],
			[$x - 1, $z, 1],
			[$x, $z + 1, 1],
			[$x, $z - 1, 1],
			[$x + Facing::OFFSET[Facing::HORIZONTAL[$directionIndex]][0], $z + Facing::OFFSET[Facing::HORIZONTAL[$directionIndex]][2], 2],
			[$x + Facing::OFFSET[Facing::HORIZONTAL[($directionIndex + 2) % count(Facing::HORIZONTAL)]][0], $z + Facing::OFFSET[Facing::HORIZONTAL[($directionIndex + 2) % count(Facing::HORIZONTAL)]][2], 1],
		];

		foreach($rootOffsets as [$rootX, $rootZ, $depth]){
			for($drop = 0; $drop < $depth; ++$drop){
				if($transaction->fetchBlockAt($rootX, $y - $drop, $rootZ)->canBeReplaced()){
					$transaction->addBlockAt($rootX, $y - $drop, $rootZ, VanillaBlocks::MANGROVE_ROOTS());
				}
			}
		}
	}

	private function placeLeafLayer(BlockTransaction $transaction, int $centerX, int $y, int $centerZ, int $radius, bool $trimCorners = false, bool $hanging = false, int $propaguleChance = 0) : void{
		for($x = -$radius; $x <= $radius; ++$x){
			for($z = -$radius; $z <= $radius; ++$z){
				if($trimCorners && abs($x) === $radius && abs($z) === $radius){
					continue;
				}
				$xx = $centerX + $x;
				$zz = $centerZ + $z;
				if($transaction->fetchBlockAt($xx, $y, $zz)->canBeReplaced()){
					$transaction->addBlockAt($xx, $y, $zz, $this->leafBlock);
				}

				if($hanging && abs($x) + abs($z) >= $radius + 1 && $transaction->fetchBlockAt($xx, $y - 1, $zz)->canBeReplaced()){
					if($propaguleChance > 0 && ((abs($x) + abs($z) + $xx + $zz) % $propaguleChance) === 0){
						$transaction->addBlockAt($xx, $y - 1, $zz, VanillaBlocks::MANGROVE_PROPAGULE()->setHanging(true)->setStage(4));
					}
				}
			}
		}
	}
}
