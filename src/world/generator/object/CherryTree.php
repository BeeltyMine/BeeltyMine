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
use function max;

final class CherryTree extends Tree{
	/** @var Vector3[] */
	private array $canopyCenters = [];

	public function __construct(){
		parent::__construct(VanillaBlocks::CHERRY_LOG(), VanillaBlocks::CHERRY_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $random->nextBoundedInt(3) + 5;
		$this->canopyCenters = [];
		return parent::getBlockTransaction($world, $x, $y, $z, $random);
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		for($yy = 0; $yy < $trunkHeight; ++$yy){
			$transaction->addBlockAt($x, $y + $yy, $z, $this->trunkBlock);
		}

		$top = new Vector3($x, $y + $trunkHeight - 1, $z);
		$this->canopyCenters[] = $top;

		$primaryIndex = $random->nextRange(0, count(Facing::HORIZONTAL) - 1);
		$primaryDirection = Facing::HORIZONTAL[$primaryIndex];
		$this->extendBranch($transaction, $x, $y + $trunkHeight - 2, $z, $primaryDirection, 2);

		$secondaryDirection = Facing::HORIZONTAL[($primaryIndex + 1 + $random->nextBoundedInt(2)) % count(Facing::HORIZONTAL)];
		if($secondaryDirection !== $primaryDirection){
			$this->extendBranch($transaction, $x, $y + $trunkHeight - 3, $z, $secondaryDirection, 1);
		}
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		foreach($this->canopyCenters as $index => $center){
			$radius = $index === 0 ? 2 : 1;
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() + 1, $center->getFloorZ(), 1);
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY(), $center->getFloorZ(), $radius + 1, trimCorners: true);
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() - 1, $center->getFloorZ(), $radius + 1, trimCorners: true, carveCross: true);
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() - 2, $center->getFloorZ(), $radius, trimCorners: true, hangLeaves: true);
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

	private function placeLeafLayer(BlockTransaction $transaction, int $centerX, int $y, int $centerZ, int $radius, bool $trimCorners = false, bool $hangLeaves = false, bool $carveCross = false) : void{
		for($x = -$radius; $x <= $radius; ++$x){
			for($z = -$radius; $z <= $radius; ++$z){
				if($trimCorners && abs($x) === $radius && abs($z) === $radius){
					continue;
				}
				if($carveCross && abs($x) === $radius && abs($z) === 0){
					continue;
				}
				if($carveCross && abs($z) === $radius && abs($x) === 0){
					continue;
				}

				$xx = $centerX + $x;
				$zz = $centerZ + $z;
				if($transaction->fetchBlockAt($xx, $y, $zz)->canBeReplaced()){
					$transaction->addBlockAt($xx, $y, $zz, $this->leafBlock);
				}

				if($hangLeaves && abs($x) + abs($z) >= max(1, $radius) + 1 && $transaction->fetchBlockAt($xx, $y - 1, $zz)->canBeReplaced()){
					$transaction->addBlockAt($xx, $y - 1, $zz, $this->leafBlock);
				}
			}
		}
	}
}
