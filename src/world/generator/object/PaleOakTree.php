<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\World;
use function abs;
use function count;

final class PaleOakTree extends Tree{
	private bool $tryCreakingHeart = false;
	private int $mossSeed = 0;

	public function __construct(){
		parent::__construct(VanillaBlocks::PALE_OAK_LOG(), VanillaBlocks::PALE_OAK_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$height = $random->nextRange(0, 1) + $random->nextRange(0, 1) + 6;
		$this->treeHeight = $height;

		if($y < World::Y_MIN + 1 || $y + $height + 1 >= World::Y_MAX){
			return null;
		}

		$groundType = $world->getBlockAt($x, $y - 1, $z)->getTypeId();
		if($groundType !== BlockTypeIds::GRASS && $groundType !== BlockTypeIds::DIRT){
			return null;
		}

		if(!$this->placeTreeOfHeight($world, $x, $y, $z, $height)){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$this->mossSeed = $world instanceof World ? $world->getSeed() : 0;

		$this->setDirtAt($transaction, $x, $y - 1, $z);
		$this->setDirtAt($transaction, $x + 1, $y - 1, $z);
		$this->setDirtAt($transaction, $x, $y - 1, $z + 1);
		$this->setDirtAt($transaction, $x + 1, $y - 1, $z + 1);

		$direction = Facing::HORIZONTAL[$random->nextRange(0, count(Facing::HORIZONTAL) - 1)];
		$trunkBendStart = $height - $random->nextBoundedInt(4);
		$bendSteps = 2 - $random->nextBoundedInt(3);
		$trunkX = $x;
		$trunkZ = $z;
		$topY = $y + $height - 1;

		for($offsetY = 0; $offsetY < $height; ++$offsetY){
			if($offsetY >= $trunkBendStart && $bendSteps > 0){
				$trunkX += Facing::OFFSET[$direction][0];
				$trunkZ += Facing::OFFSET[$direction][2];
				--$bendSteps;
			}

			$currentY = $y + $offsetY;
			if($this->canGrowInto($transaction->fetchBlockAt($trunkX, $currentY, $trunkZ))){
				$creakingIndex = -1;
				if($this->tryCreakingHeart && $currentY > $y && $random->nextBoundedInt($height) === 0){
					$this->tryCreakingHeart = false;
					$creakingIndex = $random->nextRange(0, 3);
				}

				$this->placeLogAt($transaction, $trunkX, $currentY, $trunkZ, $creakingIndex === 0);
				$this->placeLogAt($transaction, $trunkX + 1, $currentY, $trunkZ, $creakingIndex === 1);
				$this->placeLogAt($transaction, $trunkX, $currentY, $trunkZ + 1, $creakingIndex === 2);
				$this->placeLogAt($transaction, $trunkX + 1, $currentY, $trunkZ + 1, $creakingIndex === 3);
			}
		}

		for($offX = -2; $offX <= 0; ++$offX){
			for($offZ = -2; $offZ <= 0; ++$offZ){
				$this->placeLeafAt($transaction, $trunkX + $offX, $topY - 1, $trunkZ + $offZ);
				$this->placeLeafAt($transaction, 1 + $trunkX - $offX, $topY - 1, $trunkZ + $offZ);
				$this->placeLeafAt($transaction, $trunkX + $offX, $topY - 1, 1 + $trunkZ - $offZ);
				$this->placeLeafAt($transaction, 1 + $trunkX - $offX, $topY - 1, 1 + $trunkZ - $offZ);

				if(($offX > -2 || $offZ > -1) && ($offX !== -1 || $offZ !== -2)){
					$this->placeLeafAt($transaction, $trunkX + $offX, $topY + 1, $trunkZ + $offZ);
					$this->placeLeafAt($transaction, 1 + $trunkX - $offX, $topY + 1, $trunkZ + $offZ);
					$this->placeLeafAt($transaction, $trunkX + $offX, $topY + 1, 1 + $trunkZ - $offZ);
					$this->placeLeafAt($transaction, 1 + $trunkX - $offX, $topY + 1, 1 + $trunkZ - $offZ);
				}
			}
		}

		if($random->nextBoolean()){
			$this->placeLeafAt($transaction, $trunkX, $topY + 2, $trunkZ);
			$this->placeLeafAt($transaction, $trunkX + 1, $topY + 2, $trunkZ);
			$this->placeLeafAt($transaction, $trunkX + 1, $topY + 2, $trunkZ + 1);
			$this->placeLeafAt($transaction, $trunkX, $topY + 2, $trunkZ + 1);
		}

		for($offX = -3; $offX <= 4; ++$offX){
			for($offZ = -3; $offZ <= 4; ++$offZ){
				if(
					($offX !== -3 || $offZ !== -3) &&
					($offX !== -3 || $offZ !== 4) &&
					($offX !== 4 || $offZ !== -3) &&
					($offX !== 4 || $offZ !== 4) &&
					(abs($offX) < 3 || abs($offZ) < 3)
				){
					$this->placeLeafAt($transaction, $trunkX + $offX, $topY, $trunkZ + $offZ);
				}
			}
		}

		for($offX = -1; $offX <= 2; ++$offX){
			for($offZ = -1; $offZ <= 2; ++$offZ){
				if(($offX < 0 || $offX > 1 || $offZ < 0 || $offZ > 1) && $random->nextBoundedInt(3) === 0){
					$columnDepth = $random->nextBoundedInt(3) + 2;

					for($step = 0; $step < $columnDepth; ++$step){
						$this->placeLogAt($transaction, $x + $offX, $topY - $step - 1, $z + $offZ);
					}

					for($ringX = -1; $ringX <= 1; ++$ringX){
						for($ringZ = -1; $ringZ <= 1; ++$ringZ){
							$this->placeLeafAt($transaction, $trunkX + $offX + $ringX, $topY, $trunkZ + $offZ + $ringZ);
						}
					}

					for($ringX = -2; $ringX <= 2; ++$ringX){
						for($ringZ = -2; $ringZ <= 2; ++$ringZ){
							if(abs($ringX) !== 2 || abs($ringZ) !== 2){
								$this->placeLeafAt($transaction, $trunkX + $offX + $ringX, $topY - 1, $trunkZ + $offZ + $ringZ);
							}
						}
					}
				}
			}
		}

		return $transaction;
	}

	private function placeTreeOfHeight(ChunkManager $world, int $x, int $y, int $z, int $height) : bool{
		for($layer = 0; $layer <= $height + 1; ++$layer){
			$radius = 1;
			if($layer === 0){
				$radius = 0;
			}
			if($layer >= $height - 1){
				$radius = 2;
			}

			for($offX = -$radius; $offX <= $radius; ++$offX){
				for($offZ = -$radius; $offZ <= $radius; ++$offZ){
					if(!$this->canGrowInto($world->getBlockAt($x + $offX, $y + $layer, $z + $offZ))){
						return false;
					}
				}
			}
		}

		return true;
	}

	private function setDirtAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		$transaction->addBlockAt($x, $y, $z, VanillaBlocks::DIRT());
	}

	private function placeLogAt(BlockTransaction $transaction, int $x, int $y, int $z, bool $creaking = false) : void{
		if($this->canGrowInto($transaction->fetchBlockAt($x, $y, $z))){
			$transaction->addBlockAt($x, $y, $z, $this->trunkBlock);
		}
	}

	private function placeLeafAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		if($transaction->fetchBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::AIR){
			return;
		}

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
