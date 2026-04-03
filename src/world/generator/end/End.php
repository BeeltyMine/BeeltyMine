<?php

/*
 *     ____            ____        __  ____
 *    / __ )___  ___  / / /___  __/  |/  (_)___  ___
 *   / __  / _ \/ _ \/ / __/ / / / /|_/ / / __ \/ _ \
 *  / /_/ /  __/  __/ / /_/ /_/ / /  / / / / / /  __/
 * /_____/\___/\___/_/\__/\__, /_/  /_/_/_/ /_/\___/
 *                       /____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Ayrz
 *
 */

declare(strict_types=1);

namespace pocketmine\world\generator\end;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\generator\noise\Simplex;
use pocketmine\world\generator\structure\StructureRegistry;
use pocketmine\world\World;
use function abs;
use function max;
use function min;
use function round;
use function sqrt;

final class End extends Generator{
	public const CENTER_X = 256;
	public const CENTER_Z = 256;

	private const MAIN_ISLAND_RADIUS = 96;
	private const OUTER_ISLAND_START_RADIUS = 160;
	private const SPAWN_PLATFORM_Y = 64;
	private const SPAWN_PLATFORM_RADIUS = 4;
	private const OBSIDIAN_PILLARS = [
		[42, 0, 34, 2],
		[30, 30, 40, 2],
		[0, 42, 46, 3],
		[-30, 30, 38, 2],
		[-42, 0, 36, 2],
		[-30, -30, 44, 3],
		[0, -42, 32, 2],
		[30, -30, 42, 3],
	];

	private Simplex $heightNoise;
	private Simplex $islandNoise;

	/**
	 * @throws InvalidGeneratorOptionsException
	 */
	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);

		$this->heightNoise = new Simplex($this->random, 3, 1 / 2, 1 / 72);
		$this->islandNoise = new Simplex($this->random, 2, 1 / 2, 1 / 120);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");
		$endStone = VanillaBlocks::END_STONE()->getStateId();

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$worldX = ($chunkX << Chunk::COORD_BIT_SIZE) + $x;
				$worldZ = ($chunkZ << Chunk::COORD_BIT_SIZE) + $z;

				for($y = World::Y_MIN; $y < World::Y_MAX; ++$y){
					$chunk->setBiomeId($x, $y, $z, BiomeIds::THE_END);
				}

				[$bottom, $top] = $this->getColumnBounds($worldX, $worldZ);
				if($bottom === null || $top === null){
					continue;
				}

				for($y = $bottom; $y <= $top; ++$y){
					$chunk->setBlockStateId($x, $y, $z, $endStone);
				}
			}
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->populateSpawnIsland($world, $chunkX, $chunkZ);
		StructureRegistry::populateChunk($world, $chunkX, $chunkZ, DimensionIds::THE_END, $this->seed);
	}

	/**
	 * @return array{int|null, int|null}
	 */
	private function getColumnBounds(int $worldX, int $worldZ) : array{
		$relativeX = $worldX - self::CENTER_X;
		$relativeZ = $worldZ - self::CENTER_Z;
		$distance = sqrt(($relativeX * $relativeX) + ($relativeZ * $relativeZ));
		$heightNoise = $this->heightNoise->noise2D($worldX, $worldZ, true);

		if($distance < self::MAIN_ISLAND_RADIUS){
			$strength = 1 - ($distance / self::MAIN_ISLAND_RADIUS);
			$top = 63 + (int) round(($strength * 8) + ($heightNoise * 3));
			$thickness = 10 + (int) round(($strength * 12) + (abs($heightNoise) * 2));
			return [$top - $thickness, $top];
		}

		if($distance < self::OUTER_ISLAND_START_RADIUS){
			return [null, null];
		}

		$islandNoise = ($this->islandNoise->noise2D($worldX, $worldZ, true) + 1) / 2;
		$threshold = $distance < 320 ? 0.84 : 0.77;
		if($islandNoise < $threshold){
			return [null, null];
		}

		$strength = ($islandNoise - $threshold) / (1 - $threshold);
		$top = 64 + (int) round(($strength * 20) + ($heightNoise * 5));
		$thickness = 5 + (int) round($strength * 10);

		return [max(8, $top - $thickness), min(110, $top)];
	}

	private function populateSpawnIsland(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		if($this->ownsFeatureChunk($chunkX, $chunkZ, self::CENTER_X, self::CENTER_Z)){
			$this->generateSpawnPlatform($world);
		}

		foreach(self::OBSIDIAN_PILLARS as [$offsetX, $offsetZ, $height, $radius]){
			$pillarX = self::CENTER_X + $offsetX;
			$pillarZ = self::CENTER_Z + $offsetZ;
			if($this->ownsFeatureChunk($chunkX, $chunkZ, $pillarX, $pillarZ)){
				$this->generateObsidianPillar($world, $pillarX, $pillarZ, $height, $radius);
			}
		}
	}

	private function ownsFeatureChunk(int $chunkX, int $chunkZ, int $worldX, int $worldZ) : bool{
		return $chunkX === ($worldX >> Chunk::COORD_BIT_SIZE) && $chunkZ === ($worldZ >> Chunk::COORD_BIT_SIZE);
	}

	private function generateSpawnPlatform(ChunkManager $world) : void{
		$obsidian = VanillaBlocks::OBSIDIAN();
		$bedrock = VanillaBlocks::BEDROCK();
		$air = VanillaBlocks::AIR();
		$platformY = max(self::SPAWN_PLATFORM_Y, ($this->findSurfaceY($world, self::CENTER_X, self::CENTER_Z) ?? self::SPAWN_PLATFORM_Y) + 1);

		for($x = self::CENTER_X - self::SPAWN_PLATFORM_RADIUS - 1; $x <= self::CENTER_X + self::SPAWN_PLATFORM_RADIUS + 1; ++$x){
			for($z = self::CENTER_Z - self::SPAWN_PLATFORM_RADIUS - 1; $z <= self::CENTER_Z + self::SPAWN_PLATFORM_RADIUS + 1; ++$z){
				$distanceSquared = (($x - self::CENTER_X) ** 2) + (($z - self::CENTER_Z) ** 2);
				if($distanceSquared > ((self::SPAWN_PLATFORM_RADIUS + 1) ** 2)){
					continue;
				}

				for($y = $platformY + 1; $y <= $platformY + 7; ++$y){
					$this->setBlock($world, $x, $y, $z, $air);
				}

				if($distanceSquared <= (self::SPAWN_PLATFORM_RADIUS ** 2)){
					$this->setBlock($world, $x, $platformY, $z, $obsidian);
				}
			}
		}

		for($y = $platformY; $y <= $platformY + 3; ++$y){
			$this->setBlock($world, self::CENTER_X, $y, self::CENTER_Z, $bedrock);
		}
	}

	private function generateObsidianPillar(ChunkManager $world, int $centerX, int $centerZ, int $height, int $radius) : void{
		$surfaceY = $this->findSurfaceY($world, $centerX, $centerZ);
		$baseY = max(self::SPAWN_PLATFORM_Y, $surfaceY ?? self::SPAWN_PLATFORM_Y);
		$topY = min(World::Y_MAX - 2, $baseY + $height);
		$obsidian = VanillaBlocks::OBSIDIAN();
		$bedrock = VanillaBlocks::BEDROCK();

		for($x = $centerX - $radius; $x <= $centerX + $radius; ++$x){
			for($z = $centerZ - $radius; $z <= $centerZ + $radius; ++$z){
				if((($x - $centerX) ** 2) + (($z - $centerZ) ** 2) > ($radius * $radius)){
					continue;
				}

				for($y = $baseY; $y <= $topY; ++$y){
					$this->setBlock($world, $x, $y, $z, $obsidian);
				}
			}
		}

		$this->setBlock($world, $centerX, $topY + 1, $centerZ, $bedrock);
	}

	private function findSurfaceY(ChunkManager $world, int $x, int $z) : ?int{
		for($y = 120; $y >= 32; --$y){
			$block = $world->getBlockAt($x, $y, $z);
			if($block->canBeReplaced()){
				continue;
			}
			if($world->getBlockAt($x, $y + 1, $z)->canBeReplaced()){
				return $y;
			}
		}

		return null;
	}

	private function setBlock(ChunkManager $world, int $x, int $y, int $z, Block $block) : void{
		if($world->isInWorld($x, $y, $z)){
			$world->setBlockAt($x, $y, $z, clone $block);
		}
	}
}
