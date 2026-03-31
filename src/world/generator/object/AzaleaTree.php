<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DirtType;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function count;
use function max;
use function min;

final class AzaleaTree extends Tree{
	/** @var Vector3[] */
	private array $foliageAttachments = [];

	private const TREE_HEIGHT_BASE = 4;
	private const TREE_HEIGHT_RANDOM = 3;
	private const MIN_HEIGHT_FOR_LEAVES = 3;

	private const LEADUP_SMALL = 2;
	private const LEADUP_LARGE = 3;
	private const SIDE_UP_STEPS = 2;

	public function __construct(){
		parent::__construct(VanillaBlocks::OAK_LOG(), VanillaBlocks::AZALEA_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $random->nextBoundedInt(self::TREE_HEIGHT_RANDOM) + self::TREE_HEIGHT_BASE;
		$this->foliageAttachments = [];
		return parent::getBlockTransaction($world, $x, $y, $z, $random);
	}

	protected function generateTrunkHeight(Random $random) : int{
		return min(self::TREE_HEIGHT_BASE + $random->nextBoundedInt(2), 5);
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT()->setDirtType(DirtType::ROOTED));

		$direction = Facing::HORIZONTAL[$random->nextRange(0, count(Facing::HORIZONTAL) - 1)];
		$branchStart = max(2, $trunkHeight - 2);
		for($yy = 0; $yy < $branchStart; ++$yy){
			$transaction->addBlockAt($x, $y + $yy, $z, $this->trunkBlock);
		}

		$cx = $x;
		$cy = $y + $branchStart;
		$cz = $z;
		for($i = 0; $i < min(2, $trunkHeight - $branchStart + 1); ++$i){
			$cx += Facing::OFFSET[$direction][0];
			$cz += Facing::OFFSET[$direction][2];
			$transaction->addBlockAt($cx, $cy, $cz, $this->trunkBlock);
			$cy++;
			$transaction->addBlockAt($cx, $cy, $cz, $this->trunkBlock);
		}

		$this->foliageAttachments[] = new Vector3($cx, $cy, $cz);
		$this->foliageAttachments[] = new Vector3($x, $y + $branchStart, $z);
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		foreach($this->foliageAttachments as $index => $attachment){
			$cx = $attachment->getFloorX();
			$cy = $attachment->getFloorY();
			$cz = $attachment->getFloorZ();

			$this->placeLeafLayer($transaction, $cx, $cy + 1, $cz, 1, $random);
			$this->placeLeafLayer($transaction, $cx, $cy, $cz, $index === 0 ? 3 : 2, $random);
			$this->placeLeafLayer($transaction, $cx, $cy - 1, $cz, 2, $random, trimCorners: true);
			$this->placeLeafLayer($transaction, $cx, $cy - 2, $cz, 1, $random, trimCorners: true);
		}
	}

	private function placeLeafLayer(BlockTransaction $transaction, int $centerX, int $y, int $centerZ, int $radius, Random $random, bool $trimCorners = false) : void{
		for($x = -$radius; $x <= $radius; ++$x){
			for($z = -$radius; $z <= $radius; ++$z){
				if($trimCorners && abs($x) === $radius && abs($z) === $radius){
					continue;
				}

				$xx = $centerX + $x;
				$zz = $centerZ + $z;
				if($transaction->fetchBlockAt($xx, $y, $zz)->canBeReplaced()){
					$transaction->addBlockAt(
						$xx,
						$y,
						$zz,
						$random->nextBoundedInt(4) === 0 ? VanillaBlocks::FLOWERING_AZALEA_LEAVES() : VanillaBlocks::AZALEA_LEAVES()
					);
				}
			}
		}
	}

	protected function canOverride(Block $block) : bool{
		return parent::canOverride($block)
			|| $block->getTypeId() === BlockTypeIds::AZALEA
			|| $block->getTypeId() === BlockTypeIds::FLOWERING_AZALEA;
	}
}
