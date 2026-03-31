<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;

final class PaleOakTree extends Tree{

	public function __construct(){
		parent::__construct(VanillaBlocks::PALE_OAK_LOG(), VanillaBlocks::PALE_OAK_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $random->nextBoundedInt(3) + 7;
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

		$this->placePaleMossPatch($transaction, $x, $y, $z);
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		$topY = $y + $this->treeHeight;

		$this->placeLeafLayer($transaction, $x, $topY, $z, 1);
		$this->placeLeafLayer($transaction, $x, $topY - 1, $z, 3);
		$this->placeLeafLayer($transaction, $x, $topY - 2, $z, 3, trimCorners: true, hanging: true);
		$this->placeLeafLayer($transaction, $x, $topY - 3, $z, 2, trimCorners: true, hanging: true);
	}

	private function placeLeafLayer(BlockTransaction $transaction, int $anchorX, int $y, int $anchorZ, int $radius, bool $trimCorners = false, bool $hanging = false) : void{
		for($x = -$radius; $x <= $radius + 1; ++$x){
			for($z = -$radius; $z <= $radius + 1; ++$z){
				if($trimCorners && abs($x) === $radius && abs($z) === $radius){
					continue;
				}

				$xx = $anchorX + $x;
				$zz = $anchorZ + $z;
				if($transaction->fetchBlockAt($xx, $y, $zz)->canBeReplaced()){
					$transaction->addBlockAt($xx, $y, $zz, $this->leafBlock);
				}

				if($hanging && abs($x) + abs($z) >= $radius + 1){
					$this->placeHangingMoss($transaction, $xx, $y - 1, $zz, 2);
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
				$ground = $transaction->fetchBlockAt($x + $dx, $y - 1, $z + $dz);
				if(!$ground->hasTypeTag(\pocketmine\block\BlockTypeTags::DIRT) && !$ground->hasTypeTag(\pocketmine\block\BlockTypeTags::MUD)){
					continue;
				}
				$transaction->addBlockAt($x + $dx, $y - 1, $z + $dz, VanillaBlocks::PALE_MOSS_BLOCK());
				if($transaction->fetchBlockAt($x + $dx, $y, $z + $dz)->canBeReplaced()){
					$transaction->addBlockAt($x + $dx, $y, $z + $dz, VanillaBlocks::PALE_MOSS_CARPET());
				}
			}
		}
	}
}
