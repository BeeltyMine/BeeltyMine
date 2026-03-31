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
use function array_rand;

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

		$branchFacing = Facing::HORIZONTAL[array_rand(Facing::HORIZONTAL)];
		$branchTip = $top->getSide($branchFacing)->up();
		if($transaction->fetchBlock($branchTip)->canBeReplaced()){
			$transaction->addBlock($branchTip, $this->trunkBlock);
			$this->canopyCenters[] = $branchTip;
		}
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		foreach($this->canopyCenters as $index => $center){
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() + 1, $center->getFloorZ(), 1);
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY(), $center->getFloorZ(), $index === 0 ? 3 : 2);
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() - 1, $center->getFloorZ(), $index === 0 ? 3 : 2, trimCorners: true);
			$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() - 2, $center->getFloorZ(), 2, trimCorners: true, hangLeaves: true);
		}
	}

	private function placeLeafLayer(BlockTransaction $transaction, int $centerX, int $y, int $centerZ, int $radius, bool $trimCorners = false, bool $hangLeaves = false) : void{
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

				if($hangLeaves && abs($x) + abs($z) >= $radius + 1 && $transaction->fetchBlockAt($xx, $y - 1, $zz)->canBeReplaced()){
					$transaction->addBlockAt($xx, $y - 1, $zz, $this->leafBlock);
				}
			}
		}
	}
}
