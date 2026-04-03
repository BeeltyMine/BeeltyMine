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

namespace pocketmine\world\generator;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

final class VoidGenerator extends Generator{
	private const PLATFORM_RADIUS = 2;
	private const PLATFORM_Y = 70;
	private const PLATFORM_CENTER_X = 256;
	private const PLATFORM_CENTER_Z = 256;

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				for($y = World::Y_MIN; $y < World::Y_MAX; ++$y){
					$chunk->setBiomeId($x, $y, $z, BiomeIds::PLAINS);
				}
			}
		}

		$platformBlock = VanillaBlocks::BEDROCK()->getStateId();
		for($localX = 0; $localX < Chunk::EDGE_LENGTH; ++$localX){
			$worldX = ($chunkX << Chunk::COORD_BIT_SIZE) + $localX;
			if($worldX < self::PLATFORM_CENTER_X - self::PLATFORM_RADIUS || $worldX > self::PLATFORM_CENTER_X + self::PLATFORM_RADIUS){
				continue;
			}

			for($localZ = 0; $localZ < Chunk::EDGE_LENGTH; ++$localZ){
				$worldZ = ($chunkZ << Chunk::COORD_BIT_SIZE) + $localZ;
				if($worldZ < self::PLATFORM_CENTER_Z - self::PLATFORM_RADIUS || $worldZ > self::PLATFORM_CENTER_Z + self::PLATFORM_RADIUS){
					continue;
				}

				$chunk->setBlockStateId($localX, self::PLATFORM_Y, $localZ, $platformBlock);
			}
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		//NOOP
	}
}
