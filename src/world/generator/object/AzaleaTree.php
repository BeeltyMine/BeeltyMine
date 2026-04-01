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
use pocketmine\block\Leaves;
use pocketmine\block\utils\DirtType;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\World;

final class AzaleaTree extends Tree{
	public function __construct(){
		parent::__construct(VanillaBlocks::OAK_LOG(), VanillaBlocks::AZALEA_LEAVES(), 0);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $random->nextBoundedInt(2) + 2;

		if($y < World::Y_MIN + 1 || $y + $this->treeHeight + 2 >= World::Y_MAX){
			return null;
		}

		$transaction = new BlockTransaction($world);
		$topY = $y + $this->treeHeight;

		for($i = 0; $i < $this->treeHeight + 1; ++$i){
			$this->placeLogAt($transaction, $x, $y + $i, $z);
		}

		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT()->setDirtType(DirtType::ROOTED));

		$azaleaLeaves = (clone VanillaBlocks::AZALEA_LEAVES())->setNoDecay(true)->setCheckDecay(false);
		$floweringAzaleaLeaves = (clone VanillaBlocks::FLOWERING_AZALEA_LEAVES())->setNoDecay(true)->setCheckDecay(false);

		for($offX = -2; $offX <= 1; ++$offX){
			for($offZ = -2; $offZ <= 1; ++$offZ){
				$this->placeLeafSymmetric(
					$transaction,
					$x,
					$topY + 1 + $random->nextRange(0, 1),
					$z,
					$offX,
					$offZ,
					$random->nextRange(0, 1),
					$random->nextRange(0, 1),
					$random,
					$azaleaLeaves,
					$floweringAzaleaLeaves
				);

				$this->placeLeafSymmetric(
					$transaction,
					$x,
					$topY,
					$z,
					$offX,
					$offZ,
					0,
					0,
					$random,
					$azaleaLeaves,
					$floweringAzaleaLeaves
				);

				$this->placeLeafSymmetric(
					$transaction,
					$x,
					$topY + 1,
					$z,
					$offX,
					$offZ,
					0,
					0,
					$random,
					$azaleaLeaves,
					$floweringAzaleaLeaves
				);

				$this->placeLeafSymmetric(
					$transaction,
					$x,
					$topY + 2 + $random->nextRange(-1, 0),
					$z,
					$offX,
					$offZ,
					$random->nextRange(-1, 0),
					$random->nextRange(-1, 0),
					$random,
					$azaleaLeaves,
					$floweringAzaleaLeaves
				);
			}
		}

		return $transaction;
	}

	private function placeLogAt(BlockTransaction $transaction, int $x, int $y, int $z) : void{
		$typeId = $transaction->fetchBlockAt($x, $y, $z)->getTypeId();
		if(
			$typeId === BlockTypeIds::AIR ||
			$typeId === BlockTypeIds::AZALEA ||
			$typeId === BlockTypeIds::FLOWERING_AZALEA ||
			$typeId === BlockTypeIds::AZALEA_LEAVES ||
			$typeId === BlockTypeIds::FLOWERING_AZALEA_LEAVES
		){
			$transaction->addBlockAt($x, $y, $z, $this->trunkBlock);
		}
	}

	private function placeLeafAt(
		BlockTransaction $transaction,
		int $x,
		int $y,
		int $z,
		Random $random,
		Leaves $azaleaLeaves,
		Leaves $floweringAzaleaLeaves
	) : void{
		if($transaction->fetchBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR){
			$transaction->addBlockAt(
				$x,
				$y,
				$z,
				$random->nextBoundedInt(3) === 1 ? clone $floweringAzaleaLeaves : clone $azaleaLeaves
			);
		}
	}

	private function placeLeafSymmetric(
		BlockTransaction $transaction,
		int $x,
		int $y,
		int $z,
		int $offX,
		int $offZ,
		int $addX,
		int $addZ,
		Random $random,
		Leaves $azaleaLeaves,
		Leaves $floweringAzaleaLeaves
	) : void{
		$this->placeLeafAt($transaction, $x + $offX + $addX, $y, $z + $offZ + $addZ, $random, $azaleaLeaves, $floweringAzaleaLeaves);
		$this->placeLeafAt($transaction, $x - $offX + $addX, $y, $z + $offZ + $addZ, $random, $azaleaLeaves, $floweringAzaleaLeaves);
		$this->placeLeafAt($transaction, $x + $offX + $addX, $y, $z - $offZ + $addZ, $random, $azaleaLeaves, $floweringAzaleaLeaves);
		$this->placeLeafAt($transaction, $x - $offX + $addX, $y, $z - $offZ + $addZ, $random, $azaleaLeaves, $floweringAzaleaLeaves);
	}

	protected function canOverride(Block $block) : bool{
		return parent::canOverride($block)
			|| $block->getTypeId() === BlockTypeIds::AZALEA
			|| $block->getTypeId() === BlockTypeIds::FLOWERING_AZALEA;
	}
}
