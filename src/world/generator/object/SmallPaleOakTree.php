<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\World;
use function abs;
use function intdiv;

final class SmallPaleOakTree extends Tree{
	private int $mossSeed = 0;

	public function __construct(
		private int $minTreeHeight,
		private int $maxTreeHeight
	){
		parent::__construct(VanillaBlocks::PALE_OAK_LOG(), VanillaBlocks::PALE_OAK_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$height = $random->nextBoundedInt($this->maxTreeHeight) + $this->minTreeHeight;
		$this->treeHeight = $height;

		if($y < $world->getMinY() || $y + $height + 1 >= $world->getMaxY()){
			return null;
		}

		if(!$this->canPlaceOfHeight($world, $x, $y, $z, $height)){
			return null;
		}

		$groundType = $world->getBlockAt($x, $y - 1, $z)->getTypeId();
		if($groundType !== BlockTypeIds::GRASS && $groundType !== BlockTypeIds::DIRT && $groundType !== BlockTypeIds::FARMLAND){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$this->mossSeed = $world instanceof World ? $world->getSeed() : 0;
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		for($yy = $y - 3 + $height; $yy <= $y + $height; ++$yy){
			$layerOffset = $yy - ($y + $height);
			$radius = 1 - intdiv($layerOffset, 2);

			for($xx = $x - $radius; $xx <= $x + $radius; ++$xx){
				$dx = $xx - $x;
				for($zz = $z - $radius; $zz <= $z + $radius; ++$zz){
					$dz = $zz - $z;
					if(abs($dx) !== $radius || abs($dz) !== $radius || ($random->nextBoundedInt(2) !== 0 && $layerOffset !== 0)){
						$block = $transaction->fetchBlockAt($xx, $yy, $zz);
						if(
							$block->getTypeId() === BlockTypeIds::AIR ||
							$block instanceof Leaves ||
							$block->getTypeId() === BlockTypeIds::PALE_HANGING_MOSS
						){
							$this->placeLeafAt($transaction, $xx, $yy, $zz);
						}
					}
				}
			}
		}

		for($yy = 0; $yy < $height; ++$yy){
			if($this->canGrowInto($transaction->fetchBlockAt($x, $y + $yy, $z))){
				$transaction->addBlockAt($x, $y + $yy, $z, $this->trunkBlock);
			}
		}

		return $transaction;
	}

	private function canPlaceOfHeight(ChunkManager $world, int $x, int $y, int $z, int $height) : bool{
		for($layerY = $y; $layerY <= $y + 1 + $height; ++$layerY){
			$radius = 1;
			if($layerY === $y){
				$radius = 0;
			}
			if($layerY >= $y + 1 + $height - 2){
				$radius = 2;
			}

			for($offX = $x - $radius; $offX <= $x + $radius; ++$offX){
				for($offZ = $z - $radius; $offZ <= $z + $radius; ++$offZ){
					if($layerY < $world->getMinY() || $layerY >= $world->getMaxY()){
						return false;
					}
					if(!$this->canGrowInto($world->getBlockAt($offX, $layerY, $offZ))){
						return false;
					}
				}
			}
		}

		return true;
	}

	private function placeLeafAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		$leafBlock = $this->leafBlock;
		if($leafBlock instanceof Leaves){
			$leafBlock = (clone $leafBlock)->setNoDecay(true)->setCheckDecay(false);
		}
		$transaction->addBlockAt($x, $y, $z, $leafBlock);

		$mossRandom = new Random($this->mossSeed + $x + $y + $z);
		if($mossRandom->nextBoundedInt(2) === 0){
			$this->placeHangingMoss($transaction, $x, $y, $z, $mossRandom->nextRange(1, 6));
		}
	}

	private function placeHangingMoss(BlockTransaction $transaction, int $x, int $y, int $z, int $depth) : void{
		for($i = 1; $i < $depth; ++$i){
			$yy = $y - $i;
			if($transaction->fetchBlockAt($x, $yy, $z)->getTypeId() !== BlockTypeIds::AIR){
				break;
			}
			$transaction->addBlockAt($x, $yy, $z, VanillaBlocks::PALE_HANGING_MOSS()->setTip($i === $depth - 1));
		}
	}

	private function canGrowInto(Block $block) : bool{
		return $this->canOverride($block);
	}
}
