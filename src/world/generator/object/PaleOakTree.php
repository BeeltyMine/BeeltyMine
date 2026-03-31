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

final class PaleOakTree extends Tree{
	/** @var Vector3[] */
	private array $crownAnchors = [];

	public function __construct(){
		parent::__construct(VanillaBlocks::PALE_OAK_LOG(), VanillaBlocks::PALE_OAK_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $random->nextBoundedInt(3) + 7;
		$this->crownAnchors = [];
		if(!$this->canPlaceLargeTree($world, $x, $y, $z)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$this->placeTrunk($x, $y, $z, $random, $this->treeHeight, $transaction);
		$this->placeCanopy($x, $y, $z, $random, $transaction);

		return $transaction;
	}

	private function canPlaceLargeTree(ChunkManager $world, int $x, int $y, int $z) : bool{
		for($yy = 0; $yy <= $this->treeHeight + 2; ++$yy){
			for($xx = -2; $xx <= 3; ++$xx){
				for($zz = -2; $zz <= 3; ++$zz){
					if(!$this->canOverride($world->getBlockAt($x + $xx, $y + $yy, $z + $zz))){
						return false;
					}
				}
			}
		}

		return true;
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		foreach([[0, 0], [1, 0], [0, 1], [1, 1]] as [$dx, $dz]){
			$transaction->addBlockAt($x + $dx, $y - 1, $z + $dz, VanillaBlocks::DIRT());
		}

		for($yy = 0; $yy < $trunkHeight; ++$yy){
			foreach([[0, 0], [1, 0], [0, 1], [1, 1]] as [$dx, $dz]){
				$transaction->addBlockAt($x + $dx, $y + $yy, $z + $dz, $this->trunkBlock);
			}
		}

		$topY = $y + $trunkHeight - 1;
		$this->crownAnchors[] = new Vector3($x, $topY, $z);

		$primaryIndex = $random->nextRange(0, count(Facing::HORIZONTAL) - 1);
		$primaryDirection = Facing::HORIZONTAL[$primaryIndex];
		$this->extendBranch($transaction, $x, $topY - 1, $z, $primaryDirection, 2);

		$secondaryDirection = Facing::HORIZONTAL[($primaryIndex + 1 + $random->nextBoundedInt(2)) % count(Facing::HORIZONTAL)];
		if($secondaryDirection !== $primaryDirection){
			$this->extendBranch($transaction, $x, $topY - 2, $z, $secondaryDirection, 1);
		}

		$this->placePaleMossPatch($transaction, $x, $y, $z);
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		foreach($this->crownAnchors as $index => $anchor){
			$anchorX = $anchor->getFloorX();
			$anchorY = $anchor->getFloorY();
			$anchorZ = $anchor->getFloorZ();

			if($index === 0){
				$this->placeLeafLayer($transaction, $anchorX, $anchorY + 1, $anchorZ, 1.2);
				$this->placeLeafLayer($transaction, $anchorX, $anchorY, $anchorZ, 2.6);
				$this->placeLeafLayer($transaction, $anchorX, $anchorY - 1, $anchorZ, 2.9, hangingChance: 4);
				$this->placeLeafLayer($transaction, $anchorX, $anchorY - 2, $anchorZ, 2.2, hangingChance: 3);
			}else{
				$this->placeLeafLayer($transaction, $anchorX, $anchorY, $anchorZ, 1.6);
				$this->placeLeafLayer($transaction, $anchorX, $anchorY - 1, $anchorZ, 1.9, hangingChance: 5);
			}
		}
	}

	private function extendBranch(BlockTransaction $transaction, int $anchorX, int $anchorY, int $anchorZ, int $direction, int $length) : void{
		$branchX = $anchorX + (Facing::OFFSET[$direction][0] > 0 ? 1 : 0);
		$branchY = $anchorY;
		$branchZ = $anchorZ + (Facing::OFFSET[$direction][2] > 0 ? 1 : 0);

		for($step = 0; $step < $length; ++$step){
			$branchX += Facing::OFFSET[$direction][0];
			$branchZ += Facing::OFFSET[$direction][2];
			if($step > 0){
				++$branchY;
			}
			$transaction->addBlockAt($branchX, $branchY, $branchZ, $this->trunkBlock);
		}

		$this->crownAnchors[] = new Vector3($branchX, $branchY, $branchZ);
	}

	private function placeLeafLayer(BlockTransaction $transaction, int $anchorX, int $y, int $anchorZ, float $radius, int $hangingChance = 0) : void{
		$range = (int) \ceil($radius);
		for($x = -$range; $x <= $range + 1; ++$x){
			for($z = -$range; $z <= $range + 1; ++$z){
				$centeredX = abs($x - 0.5);
				$centeredZ = abs($z - 0.5);
				$distance = max($centeredX, $centeredZ) + min($centeredX, $centeredZ) * 0.35;
				if($distance > $radius){
					continue;
				}

				$xx = $anchorX + $x;
				$zz = $anchorZ + $z;
				if($transaction->fetchBlockAt($xx, $y, $zz)->canBeReplaced()){
					$transaction->addBlockAt($xx, $y, $zz, $this->leafBlock);
				}

				if(
					$hangingChance > 0 &&
					$distance >= $radius - 0.35 &&
					((abs($xx + $zz + $y) % $hangingChance) === 0)
				){
					$this->placeHangingMoss($transaction, $xx, $y - 1, $zz, 1 + (($xx + $zz + $y) & 1));
				}
			}
		}
	}

	private function placeHangingMoss(BlockTransaction $transaction, int $x, int $y, int $z, int $length) : void{
		for($i = 0; $i < $length; ++$i){
			$yy = $y - $i;
			if(!$transaction->fetchBlockAt($x, $yy, $z)->canBeReplaced()){
				break;
			}
			$transaction->addBlockAt($x, $yy, $z, VanillaBlocks::PALE_HANGING_MOSS());
		}
	}

	private function placePaleMossPatch(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		for($dx = -2; $dx <= 3; ++$dx){
			for($dz = -2; $dz <= 3; ++$dz){
				$centeredX = abs($dx - 0.5);
				$centeredZ = abs($dz - 0.5);
				$distance = max($centeredX, $centeredZ) + min($centeredX, $centeredZ) * 0.4;
				if($distance > 2.15 || ($distance > 1.65 && (($dx + $dz) & 1) === 0)){
					continue;
				}

				$ground = $transaction->fetchBlockAt($x + $dx, $y - 1, $z + $dz);
				if(!$ground->hasTypeTag(\pocketmine\block\BlockTypeTags::DIRT) && !$ground->hasTypeTag(\pocketmine\block\BlockTypeTags::MUD)){
					continue;
				}
				$transaction->addBlockAt($x + $dx, $y - 1, $z + $dz, VanillaBlocks::PALE_MOSS_BLOCK());
				if($distance > 1.15 && $transaction->fetchBlockAt($x + $dx, $y, $z + $dz)->canBeReplaced()){
					$transaction->addBlockAt($x + $dx, $y, $z + $dz, VanillaBlocks::PALE_MOSS_CARPET());
				}
			}
		}
	}
}
