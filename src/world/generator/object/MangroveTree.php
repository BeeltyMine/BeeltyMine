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

final class MangroveTree extends Tree{
	private ?Vector3 $canopyCenter = null;

	public function __construct(){
		parent::__construct(VanillaBlocks::MANGROVE_LOG(), VanillaBlocks::MANGROVE_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $random->nextBoundedInt(3) + 7;
		$this->canopyCenter = null;
		return parent::getBlockTransaction($world, $x, $y, $z, $random);
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		$direction = Facing::HORIZONTAL[array_rand(Facing::HORIZONTAL)];
		$cx = $x;
		$cy = $y;
		$cz = $z;

		for($i = 0; $i < $trunkHeight; ++$i){
			$transaction->addBlockAt($cx, $cy, $cz, $this->trunkBlock);
			if($i >= 2 && $i <= 4){
				$cx += Facing::OFFSET[$direction][0];
				$cz += Facing::OFFSET[$direction][2];
			}
			$cy++;
		}

		$this->canopyCenter = new Vector3($cx, $cy - 1, $cz);
		$this->placeRoots($transaction, $x, $y, $z);
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		$center = $this->canopyCenter ?? new Vector3($x, $y + $this->treeHeight - 1, $z);
		$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() + 1, $center->getFloorZ(), 1);
		$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY(), $center->getFloorZ(), 3);
		$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() - 1, $center->getFloorZ(), 3, trimCorners: true, hanging: true);
		$this->placeLeafLayer($transaction, $center->getFloorX(), $center->getFloorY() - 2, $center->getFloorZ(), 2, trimCorners: true, hanging: true);
	}

	private function placeRoots(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		foreach([
			[$x + 1, $z],
			[$x - 1, $z],
			[$x, $z + 1],
			[$x, $z - 1],
			[$x + 1, $z + 1],
			[$x - 1, $z - 1],
		] as [$rootX, $rootZ]){
			if($transaction->fetchBlockAt($rootX, $y, $rootZ)->canBeReplaced()){
				$transaction->addBlockAt($rootX, $y, $rootZ, VanillaBlocks::MANGROVE_ROOTS());
				if($transaction->fetchBlockAt($rootX, $y + 1, $rootZ)->canBeReplaced()){
					$transaction->addBlockAt($rootX, $y + 1, $rootZ, VanillaBlocks::MOSS_CARPET());
				}
			}
			if($transaction->fetchBlockAt($rootX, $y - 1, $rootZ)->canBeReplaced()){
				$transaction->addBlockAt($rootX, $y - 1, $rootZ, VanillaBlocks::MANGROVE_ROOTS());
			}
		}
	}

	private function placeLeafLayer(BlockTransaction $transaction, int $centerX, int $y, int $centerZ, int $radius, bool $trimCorners = false, bool $hanging = false) : void{
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
					$transaction->addBlockAt($xx, $y - 1, $zz, VanillaBlocks::MANGROVE_PROPAGULE()->setHanging(true)->setStage(4));
				}
			}
		}
	}
}
