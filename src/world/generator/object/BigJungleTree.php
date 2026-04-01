<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeTags;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function cos;
use function intdiv;
use function sin;

final class BigJungleTree extends Tree{
	public function __construct(){
		parent::__construct(VanillaBlocks::JUNGLE_LOG(), VanillaBlocks::JUNGLE_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$height = 10 + $random->nextBoundedInt(11);

		if($y < $world->getMinY() + 1 || $y + $height + 2 >= $world->getMaxY()){
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

		$this->createCrown($transaction, $x, $y + $height, $z, 2);

		for($branchY = $y + $height - 2 - $random->nextBoundedInt(4); $branchY > $y + intdiv($height, 2); $branchY -= 2 + $random->nextBoundedInt(4)){
			$angle = $random->nextFloat() * (M_PI * 2.0);
			$branchX = (int) ($x + (0.5 + cos($angle) * 4.0));
			$branchZ = (int) ($z + (0.5 + sin($angle) * 4.0));

			for($step = 0; $step < 5; ++$step){
				$logX = (int) ($x + (1.5 + cos($angle) * $step));
				$logZ = (int) ($z + (1.5 + sin($angle) * $step));
				$this->placeLogAt($transaction, $logX, $branchY - 3 + intdiv($step, 2), $logZ);
			}

			$leafOffset = 1 + $random->nextBoundedInt(2);
			for($yy = $branchY - $leafOffset; $yy <= $branchY; ++$yy){
				$radius = 1 - ($yy - $branchY);
				$this->growLeavesLayer($transaction, $branchX, $yy, $branchZ, $radius);
			}
		}

		for($yy = 0; $yy < $height; ++$yy){
			$currentY = $y + $yy;

			$this->placeLogAt($transaction, $x, $currentY, $z);
			if($yy > 0){
				$this->placeVine($transaction, $random, $x - 1, $currentY, $z, Facing::EAST);
				$this->placeVine($transaction, $random, $x, $currentY, $z - 1, Facing::SOUTH);
			}

			if($yy < $height - 1){
				$this->placeLogAt($transaction, $x + 1, $currentY, $z);
				if($yy > 0){
					$this->placeVine($transaction, $random, $x + 2, $currentY, $z, Facing::WEST);
					$this->placeVine($transaction, $random, $x + 1, $currentY, $z - 1, Facing::SOUTH);
				}

				$this->placeLogAt($transaction, $x + 1, $currentY, $z + 1);
				if($yy > 0){
					$this->placeVine($transaction, $random, $x + 2, $currentY, $z + 1, Facing::WEST);
					$this->placeVine($transaction, $random, $x + 1, $currentY, $z + 2, Facing::NORTH);
				}

				$this->placeLogAt($transaction, $x, $currentY, $z + 1);
				if($yy > 0){
					$this->placeVine($transaction, $random, $x - 1, $currentY, $z + 1, Facing::EAST);
					$this->placeVine($transaction, $random, $x, $currentY, $z + 2, Facing::NORTH);
				}
			}
		}

		return $transaction;
	}

	private function canPlaceObjectForHeight(ChunkManager $world, int $height, int $x, int $y, int $z) : bool{
		for($yy = 0; $yy <= $height + 1; ++$yy){
			$radius = $yy === 0 ? 1 : 2;

			$currentY = $y + $yy;
			if($currentY < $world->getMinY() || $currentY >= $world->getMaxY()){
				return false;
			}

			for($xx = -$radius; $xx <= $radius + 1; ++$xx){
				for($zz = -$radius; $zz <= $radius + 1; ++$zz){
					if(!$this->canGrowInto($world->getBlockAt($x + $xx, $currentY, $z + $zz))){
						return false;
					}
				}
			}
		}

		return true;
	}

	private function growLeavesLayerStrict(BlockTransaction $transaction, int $x, int $y, int $z, int $radius) : void{
		for($xx = -$radius; $xx <= $radius; ++$xx){
			for($zz = -$radius; $zz <= $radius; ++$zz){
				if(($xx * $xx) + ($zz * $zz) <= ($radius * $radius)){
					$this->placeLeafAt($transaction, $x + $xx, $y, $z + $zz);
				}
			}
		}
	}

	private function growLeavesLayer(BlockTransaction $transaction, int $x, int $y, int $z, int $radius) : void{
		for($xx = -$radius; $xx <= $radius + 1; ++$xx){
			for($zz = -$radius; $zz <= $radius + 1; ++$zz){
				$relX = $xx - 1;
				$relZ = $zz - 1;
				if(($relX * $relX) + ($relZ * $relZ) <= ($radius * $radius) + 1){
					$this->placeLeafAt($transaction, $x + $relX, $y, $z + $relZ);
				}
			}
		}
	}

	private function createCrown(BlockTransaction $transaction, int $x, int $y, int $z, int $baseRadius) : void{
		for($yy = -2; $yy <= 0; ++$yy){
			$this->growLeavesLayerStrict($transaction, $x, $y + $yy, $z, $baseRadius + 1 - $yy);
		}
	}

	private function placeVine(BlockTransaction $transaction, Random $random, int $x, int $y, int $z, int $face) : void{
		if($random->nextBoundedInt(3) > 0 && $transaction->fetchBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR){
			$transaction->addBlockAt($x, $y, $z, VanillaBlocks::VINES()->setFace($face, true));
		}
	}

	private function placeLogAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		if($this->canGrowInto($transaction->fetchBlockAt($x, $y, $z))){
			$transaction->addBlockAt($x, $y, $z, $this->trunkBlock);
		}
	}

	private function placeLeafAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		$block = $transaction->fetchBlockAt($x, $y, $z);
		if(!$this->canGrowInto($block)){
			return;
		}

		$leaf = $this->leafBlock;
		if($leaf instanceof Leaves){
			$leaf = (clone $leaf)->setNoDecay(true)->setCheckDecay(false);
		}
		$transaction->addBlockAt($x, $y, $z, $leaf);
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