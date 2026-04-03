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

namespace pocketmine\world\generator\hell;

use pocketmine\block\Block;
use pocketmine\block\NetherVines;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\generator\noise\Simplex;
use pocketmine\world\generator\object\OreType;
use pocketmine\world\generator\object\TreeFactory;
use pocketmine\world\generator\object\TreeType;
use pocketmine\world\generator\populator\Ore;
use pocketmine\world\generator\populator\Populator;
use pocketmine\world\generator\structure\StructureRegistry;
use pocketmine\world\World;
use function abs;
use function min;

class Nether extends Generator{
	private const FLOOR_Y = 0;
	private const CEILING_Y = 127;
	private const SAFE_TREE_MARGIN = 3;

	private int $waterHeight = 32;
	private int $emptyHeight = 64;
	private int $emptyAmplitude = 1;
	private float $density = 0.5;

	/** @var Populator[] */
	private array $populators = [];
	/** @var Populator[] */
	private array $generationPopulators = [];
	private Simplex $noiseBase;
	private Simplex $biomeHeatNoise;
	private Simplex $biomeHumidityNoise;
	private Simplex $biomeVarianceNoise;
	private Simplex $detailNoise;

	/**
	 * @throws InvalidGeneratorOptionsException
	 */
	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);

		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 64);
		$this->biomeHeatNoise = new Simplex($this->random, 2, 1 / 2, 1 / 144);
		$this->biomeHumidityNoise = new Simplex($this->random, 2, 1 / 2, 1 / 144);
		$this->biomeVarianceNoise = new Simplex($this->random, 2, 1 / 2, 1 / 96);
		$this->detailNoise = new Simplex($this->random, 1, 1, 1 / 24);
		$this->random->setSeed($this->seed);

		$ores = new Ore();
		$ores->setOreTypes([
			new OreType(VanillaBlocks::NETHER_QUARTZ_ORE(), VanillaBlocks::NETHERRACK(), 16, 14, 10, 117),
			new OreType(VanillaBlocks::NETHER_GOLD_ORE(), VanillaBlocks::NETHERRACK(), 10, 10, 10, 117),
			new OreType(VanillaBlocks::ANCIENT_DEBRIS(), VanillaBlocks::NETHERRACK(), 2, 3, 8, 22)
		]);
		$this->populators[] = $ores;
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);

		$noise = $this->noiseBase->getFastNoise3D(Chunk::EDGE_LENGTH, 128, Chunk::EDGE_LENGTH, 4, 8, 4, $chunkX * Chunk::EDGE_LENGTH, 0, $chunkZ * Chunk::EDGE_LENGTH);

		//TODO: why don't we just create and set the chunk here directly?
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");

		$air = Block::EMPTY_STATE_ID;
		$bedrock = VanillaBlocks::BEDROCK()->getStateId();
		$netherrack = VanillaBlocks::NETHERRACK()->getStateId();
		$stillLava = VanillaBlocks::LAVA()->getStateId();

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$worldX = ($chunkX << Chunk::COORD_BIT_SIZE) + $x;
				$worldZ = ($chunkZ << Chunk::COORD_BIT_SIZE) + $z;
				$biomeId = $this->pickBiomeId($worldX, $worldZ);

				for($y = World::Y_MIN; $y < World::Y_MAX; ++$y){
					$chunk->setBiomeId($x, $y, $z, $biomeId);
				}

				for($y = self::FLOOR_Y; $y <= self::CEILING_Y; ++$y){
					if($y === self::FLOOR_Y || $y === self::CEILING_Y){
						$chunk->setBlockStateId($x, $y, $z, $bedrock);
						continue;
					}
					$noiseValue = (abs($this->emptyHeight - $y) / $this->emptyHeight) * $this->emptyAmplitude - $noise[$x][$z][$y];
					$noiseValue -= 1 - $this->density;

					if($noiseValue > 0){
						$chunk->setBlockStateId($x, $y, $z, $netherrack);
					}elseif($y <= $this->waterHeight){
						$chunk->setBlockStateId($x, $y, $z, $stillLava);
					}else{
						$chunk->setBlockStateId($x, $y, $z, $air);
					}
				}

				$this->applyBiomeSurface($chunk, $worldX, $worldZ, $x, $z, $biomeId);
			}
		}

		foreach($this->generationPopulators as $populator){
			$populator->populate($world, $chunkX, $chunkZ, $this->random);
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);
		foreach($this->populators as $populator){
			$populator->populate($world, $chunkX, $chunkZ, $this->random);
		}

		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$surfaceY = $this->findExposedSurfaceY($chunk, $x, $z);
				if($surfaceY === null || $surfaceY <= $this->waterHeight){
					continue;
				}

				$worldX = ($chunkX << Chunk::COORD_BIT_SIZE) + $x;
				$worldZ = ($chunkZ << Chunk::COORD_BIT_SIZE) + $z;
				$biomeId = $chunk->getBiomeId($x, $surfaceY, $z);

				$this->decorateSurface($world, $x, $z, $worldX, $surfaceY, $worldZ, $biomeId);

				$ceilingY = $this->findOpenCeilingY($chunk, $x, $z);
				if($ceilingY !== null && $ceilingY - $surfaceY >= 6){
					$this->decorateCeiling($world, $worldX, $ceilingY, $worldZ, $biomeId);
				}
			}
		}

		StructureRegistry::populateChunk($world, $chunkX, $chunkZ, DimensionIds::NETHER, $this->seed);
	}

	private function pickBiomeId(int $worldX, int $worldZ) : int{
		$heat = $this->biomeHeatNoise->noise2D($worldX, $worldZ, true);
		$humidity = $this->biomeHumidityNoise->noise2D($worldX, $worldZ, true);
		$variance = $this->biomeVarianceNoise->noise2D($worldX, $worldZ, true);

		if($variance > 0.42 && $heat > -0.15){
			return BiomeIds::BASALT_DELTAS;
		}
		if($humidity < -0.32 && $variance < 0.25){
			return BiomeIds::SOULSAND_VALLEY;
		}
		if($heat + ($humidity * 0.35) > 0.22){
			return BiomeIds::CRIMSON_FOREST;
		}
		if((-$heat) + ($humidity * 0.45) > 0.22){
			return BiomeIds::WARPED_FOREST;
		}

		return BiomeIds::HELL;
	}

	private function applyBiomeSurface(Chunk $chunk, int $worldX, int $worldZ, int $x, int $z, int $biomeId) : void{
		$surfaceY = $this->findExposedSurfaceY($chunk, $x, $z);
		if($surfaceY === null || $surfaceY <= $this->waterHeight){
			return;
		}

		$detail = $this->detailNoise->noise2D($worldX, $worldZ, true);
		$netherrack = VanillaBlocks::NETHERRACK()->getStateId();
		$crimsonNylium = VanillaBlocks::CRIMSON_NYLIUM()->getStateId();
		$warpedNylium = VanillaBlocks::WARPED_NYLIUM()->getStateId();
		$soulSand = VanillaBlocks::SOUL_SAND()->getStateId();
		$soulSoil = VanillaBlocks::SOUL_SOIL()->getStateId();
		$basalt = VanillaBlocks::BASALT()->getStateId();
		$blackstone = VanillaBlocks::BLACKSTONE()->getStateId();
		$magma = VanillaBlocks::MAGMA()->getStateId();

		switch($biomeId){
			case BiomeIds::CRIMSON_FOREST:
				$this->replaceTopLayers($chunk, $x, $z, $surfaceY, [$crimsonNylium, $crimsonNylium, $netherrack]);
				break;

			case BiomeIds::WARPED_FOREST:
				$this->replaceTopLayers($chunk, $x, $z, $surfaceY, [$warpedNylium, $warpedNylium, $netherrack]);
				break;

			case BiomeIds::SOULSAND_VALLEY:
				$this->replaceTopLayers($chunk, $x, $z, $surfaceY, [
					$detail > 0.15 ? $soulSand : $soulSoil,
					$detail > -0.1 ? $soulSand : $soulSoil,
					$soulSoil,
					$netherrack
				]);
				break;

			case BiomeIds::BASALT_DELTAS:
				$this->replaceTopLayers($chunk, $x, $z, $surfaceY, [
					$detail > 0.55 ? $magma : ($detail > 0 ? $basalt : $blackstone),
					$detail > -0.2 ? $basalt : $blackstone,
					$blackstone,
					$netherrack
				]);

				$ceilingY = $this->findOpenCeilingY($chunk, $x, $z);
				if($ceilingY !== null && $ceilingY > $surfaceY + 4){
					$chunk->setBlockStateId($x, $ceilingY, $z, $detail > 0 ? $basalt : $blackstone);
				}
				break;

			default:
				if($detail > 0.72){
					$chunk->setBlockStateId($x, $surfaceY, $z, $magma);
				}
				break;
		}
	}

	private function replaceTopLayers(Chunk $chunk, int $x, int $z, int $surfaceY, array $stateIds) : void{
		foreach($stateIds as $depth => $stateId){
			$y = $surfaceY - $depth;
			if($y <= self::FLOOR_Y){
				break;
			}
			$current = $chunk->getBlockStateId($x, $y, $z);
			if($current !== Block::EMPTY_STATE_ID && $current !== VanillaBlocks::LAVA()->getStateId()){
				$chunk->setBlockStateId($x, $y, $z, $stateId);
			}
		}
	}

	private function findExposedSurfaceY(Chunk $chunk, int $x, int $z) : ?int{
		$lava = VanillaBlocks::LAVA()->getStateId();

		for($y = self::CEILING_Y - 1; $y > self::FLOOR_Y; --$y){
			$current = $chunk->getBlockStateId($x, $y, $z);
			if($current === Block::EMPTY_STATE_ID || $current === $lava){
				continue;
			}

			if($chunk->getBlockStateId($x, $y + 1, $z) === Block::EMPTY_STATE_ID){
				return $y;
			}
		}

		return null;
	}

	private function findOpenCeilingY(Chunk $chunk, int $x, int $z) : ?int{
		$lava = VanillaBlocks::LAVA()->getStateId();

		for($y = self::CEILING_Y - 1; $y > self::FLOOR_Y + 1; --$y){
			$current = $chunk->getBlockStateId($x, $y, $z);
			if($current === Block::EMPTY_STATE_ID || $current === $lava){
				continue;
			}

			if($chunk->getBlockStateId($x, $y - 1, $z) === Block::EMPTY_STATE_ID){
				return $y;
			}
		}

		return null;
	}

	private function decorateSurface(ChunkManager $world, int $localX, int $localZ, int $worldX, int $surfaceY, int $worldZ, int $biomeId) : void{
		switch($biomeId){
			case BiomeIds::CRIMSON_FOREST:
				if($this->isSafeForHugeFungus($localX, $localZ) && $this->random->nextBoundedInt(22) === 0 && $this->tryPlaceHugeFungus($world, $worldX, $surfaceY + 1, $worldZ, TreeType::CRIMSON)){
					return;
				}

				if($this->random->nextBoundedInt(4) === 0){
					$this->tryPlaceBlockAbove($world, $worldX, $surfaceY + 1, $worldZ, $this->random->nextBoolean() ? VanillaBlocks::CRIMSON_ROOTS() : VanillaBlocks::CRIMSON_FUNGUS());
				}elseif($this->random->nextBoundedInt(18) === 0){
					$this->tryPlaceBlockAbove($world, $worldX, $surfaceY + 1, $worldZ, VanillaBlocks::FIRE());
				}
				break;

			case BiomeIds::WARPED_FOREST:
				if($this->isSafeForHugeFungus($localX, $localZ) && $this->random->nextBoundedInt(24) === 0 && $this->tryPlaceHugeFungus($world, $worldX, $surfaceY + 1, $worldZ, TreeType::WARPED)){
					return;
				}

				if($this->random->nextBoundedInt(4) === 0){
					$block = match($this->random->nextBoundedInt(3)){
						0 => VanillaBlocks::WARPED_ROOTS(),
						1 => VanillaBlocks::NETHER_SPROUTS(),
						default => VanillaBlocks::WARPED_FUNGUS(),
					};
					$this->tryPlaceBlockAbove($world, $worldX, $surfaceY + 1, $worldZ, $block);
				}elseif($this->random->nextBoundedInt(10) === 0){
					$this->tryPlaceTwistingVines($world, $worldX, $surfaceY + 1, $worldZ);
				}
				break;

			case BiomeIds::SOULSAND_VALLEY:
				if($this->random->nextBoundedInt(7) === 0){
					$this->tryPlaceBlockAbove($world, $worldX, $surfaceY + 1, $worldZ, VanillaBlocks::SOUL_FIRE());
				}
				break;

			case BiomeIds::BASALT_DELTAS:
				if($this->random->nextBoundedInt(6) === 0){
					$this->tryPlaceBasaltPillar($world, $worldX, $surfaceY + 1, $worldZ);
				}elseif($this->random->nextBoundedInt(8) === 0){
					$world->setBlockAt($worldX, $surfaceY, $worldZ, VanillaBlocks::MAGMA());
				}
				break;

			default:
				if($this->random->nextBoundedInt(16) === 0){
					$this->tryPlaceBlockAbove($world, $worldX, $surfaceY + 1, $worldZ, VanillaBlocks::FIRE());
				}
				break;
		}
	}

	private function decorateCeiling(ChunkManager $world, int $worldX, int $ceilingY, int $worldZ, int $biomeId) : void{
		if($biomeId === BiomeIds::CRIMSON_FOREST && $this->random->nextBoundedInt(10) === 0){
			$this->tryPlaceWeepingVines($world, $worldX, $ceilingY - 1, $worldZ);
		}

		if(($biomeId === BiomeIds::HELL || $biomeId === BiomeIds::CRIMSON_FOREST) && $this->random->nextBoundedInt(12) === 0){
			$this->tryPlaceGlowstoneCluster($world, $worldX, $ceilingY - 1, $worldZ);
		}
	}

	private function isSafeForHugeFungus(int $localX, int $localZ) : bool{
		return $localX >= self::SAFE_TREE_MARGIN &&
			$localZ >= self::SAFE_TREE_MARGIN &&
			$localX < Chunk::EDGE_LENGTH - self::SAFE_TREE_MARGIN &&
			$localZ < Chunk::EDGE_LENGTH - self::SAFE_TREE_MARGIN;
	}

	private function tryPlaceHugeFungus(ChunkManager $world, int $x, int $y, int $z, TreeType $type) : bool{
		if($y >= self::CEILING_Y - 8){
			return false;
		}

		$tree = TreeFactory::get($this->random, $type);
		$transaction = $tree?->getBlockTransaction($world, $x, $y, $z, $this->random);

		return $transaction?->apply() ?? false;
	}

	private function tryPlaceBlockAbove(ChunkManager $world, int $x, int $y, int $z, Block $block) : bool{
		if(!$world->isInWorld($x, $y, $z) || !$world->getBlockAt($x, $y, $z)->canBeReplaced()){
			return false;
		}

		$world->setBlockAt($x, $y, $z, $block);
		return true;
	}

	private function tryPlaceTwistingVines(ChunkManager $world, int $x, int $y, int $z) : void{
		$height = min(6, $this->random->nextBoundedInt(4) + 2);

		for($i = 0; $i < $height; ++$i){
			$blockY = $y + $i;
			if(!$world->isInWorld($x, $blockY, $z) || !$world->getBlockAt($x, $blockY, $z)->canBeReplaced()){
				break;
			}

			$world->setBlockAt($x, $blockY, $z, VanillaBlocks::TWISTING_VINES()->setAge(min(NetherVines::MAX_AGE, 23 + $i)));
		}
	}

	private function tryPlaceWeepingVines(ChunkManager $world, int $x, int $y, int $z) : void{
		$height = min(8, $this->random->nextBoundedInt(5) + 2);

		for($i = 0; $i < $height; ++$i){
			$blockY = $y - $i;
			if(!$world->isInWorld($x, $blockY, $z) || !$world->getBlockAt($x, $blockY, $z)->canBeReplaced()){
				break;
			}

			$world->setBlockAt($x, $blockY, $z, VanillaBlocks::WEEPING_VINES()->setAge(min(NetherVines::MAX_AGE, 23 + $i)));
		}
	}

	private function tryPlaceBasaltPillar(ChunkManager $world, int $x, int $y, int $z) : void{
		$height = $this->random->nextBoundedInt(4) + 2;

		for($i = 0; $i < $height; ++$i){
			$blockY = $y + $i;
			if(!$world->isInWorld($x, $blockY, $z) || !$world->getBlockAt($x, $blockY, $z)->canBeReplaced()){
				break;
			}

			$world->setBlockAt($x, $blockY, $z, $i === $height - 1 && $this->random->nextBoundedInt(4) === 0 ? VanillaBlocks::MAGMA() : VanillaBlocks::BASALT());
		}
	}

	private function tryPlaceGlowstoneCluster(ChunkManager $world, int $x, int $y, int $z) : void{
		if(!$world->isInWorld($x, $y, $z) || !$world->getBlockAt($x, $y, $z)->canBeReplaced()){
			return;
		}

		$world->setBlockAt($x, $y, $z, VanillaBlocks::GLOWSTONE());
		foreach([[1, 0], [-1, 0], [0, 1], [0, -1], [0, -1], [0, 0]] as [$dx, $dz]){
			if($this->random->nextBoolean()){
				$targetY = $dx === 0 && $dz === 0 ? $y - 1 : $y;
				if($world->isInWorld($x + $dx, $targetY, $z + $dz) && $world->getBlockAt($x + $dx, $targetY, $z + $dz)->canBeReplaced()){
					$world->setBlockAt($x + $dx, $targetY, $z + $dz, VanillaBlocks::GLOWSTONE());
				}
			}
		}
	}
}
