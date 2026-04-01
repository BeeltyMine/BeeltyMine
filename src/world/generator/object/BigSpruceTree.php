<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeTags;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function count;

final class BigSpruceTree extends Tree{
	/**
	 * Ported from PowerNukkitX ObjectBigSpruceTree foliage radius presets.
	 *
	 * @var list<list<int>>
	 */
	private const FOLIAGES = [
		[1, 0, 0, 1, 2, 1, 1, 2, 3, 2, 2, 3, 4, 3],
		[1, 0, 1, 2, 1, 2, 1, 1, 2, 3, 2, 2, 3, 4, 3],
		[1, 2, 3],
		[1, 2, 1, 3, 2, 4, 3],
	];

	public function __construct(){
		parent::__construct(VanillaBlocks::SPRUCE_LOG(), VanillaBlocks::SPRUCE_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$height = 24 + $random->nextBoundedInt(8);
		$baseX = $x;
		$baseY = $y;
		$baseZ = $z;
		$midX = $baseX + 1;
		$midZ = $baseZ + 1;

		if($baseY < $world->getMinY() + 1 || $baseY + $height + 5 >= $world->getMaxY()){
			return null;
		}

		$groundType = $world->getBlockAt($baseX, $baseY - 1, $baseZ)->getTypeId();
		if($groundType !== BlockTypeIds::GRASS && $groundType !== BlockTypeIds::DIRT && $groundType !== BlockTypeIds::PODZOL){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$this->placePodzolPatch($world, $transaction, $midX, $midZ);

		$leafRadii = self::FOLIAGES[$random->nextBoundedInt(count(self::FOLIAGES) - 1)];

		$this->placeLeafAt($transaction, $baseX, $baseY + $height + 1, $baseZ);
		$this->placeLeafAt($transaction, $baseX + 1, $baseY + $height + 1, $baseZ);
		$this->placeLeafAt($transaction, $baseX, $baseY + $height + 1, $baseZ + 1);
		$this->placeLeafAt($transaction, $baseX + 1, $baseY + $height + 1, $baseZ + 1);

		for($yy = $height; $yy >= 0; --$yy){
			$currentY = $baseY + $yy;

			$this->placeLogAt($transaction, $baseX, $currentY, $baseZ);
			$this->placeLogAt($transaction, $baseX + 1, $currentY, $baseZ);
			$this->placeLogAt($transaction, $baseX, $currentY, $baseZ + 1);
			$this->placeLogAt($transaction, $baseX + 1, $currentY, $baseZ + 1);

			$index = $height - $yy;
			if($index < count($leafRadii)){
				$radius = $leafRadii[$index];
				for($xOff = -$radius - 1; $xOff <= $radius; ++$xOff){
					for($zOff = -$radius - 1; $zOff <= $radius; ++$zOff){
						$calcX = $xOff + 0.5;
						$calcZ = $zOff + 0.5;
						$calcRadius = $radius + 0.7;
						if(($calcX * $calcX) + ($calcZ * $calcZ) < ($calcRadius * $calcRadius)){
							$this->placeLeafAt($transaction, $midX + $xOff, $currentY, $midZ + $zOff);
						}
					}
				}
			}
		}

		return $transaction;
	}

	private function placePodzolPatch(ChunkManager $world, BlockTransaction $transaction, int $midX, int $midZ) : void{
		$radius = 6;
		for($xOff = -$radius - 1; $xOff <= $radius; ++$xOff){
			for($zOff = -$radius - 1; $zOff <= $radius; ++$zOff){
				$calcX = $xOff + 0.5;
				$calcZ = $zOff + 0.5;
				$calcRadius = $radius + 0.8;
				if(($calcX * $calcX) + ($calcZ * $calcZ) >= ($calcRadius * $calcRadius)){
					continue;
				}

				$x = $midX + $xOff;
				$z = $midZ + $zOff;
				$chunk = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
				$surfaceY = $chunk?->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
				if($surfaceY !== null){
					$this->placePodzolAt($transaction, $x, $surfaceY, $z);
				}
			}
		}
	}

	private function placePodzolAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		if($transaction->fetchBlockAt($x, $y, $z)->hasTypeTag(BlockTypeTags::DIRT)){
			$transaction->addBlockAt($x, $y, $z, VanillaBlocks::PODZOL());
		}
	}

	private function placeLogAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		if($this->canOverride($transaction->fetchBlockAt($x, $y, $z))){
			$transaction->addBlockAt($x, $y, $z, $this->trunkBlock);
		}
	}

	private function placeLeafAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		$typeId = $transaction->fetchBlockAt($x, $y, $z)->getTypeId();
		if($typeId === BlockTypeIds::AIR || $typeId === BlockTypeIds::SNOW_LAYER){
			$leaf = clone $this->leafBlock;
			if($leaf instanceof Leaves){
				$leaf->setNoDecay(true)->setCheckDecay(false);
			}
			$transaction->addBlockAt($x, $y, $z, $leaf);
		}
	}
}