<?php

declare(strict_types=1);

namespace pocketmine\world\generator\structure;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\end\End;
use pocketmine\world\World;
use function abs;
use function array_values;
use function array_unique;
use function crc32;
use function intdiv;
use function max;
use function min;
use function sqrt;

final class StructureRegistry{
	private function __construct(){
		//NOOP
	}

	/**
	 * @return list<StructureType>
	 */
	public static function getStructuresForWorld(World $world) : array{
		$result = [];
		foreach(StructureType::cases() as $type){
			if($type->getDimensionId() === $world->getDimensionId()){
				$result[] = $type;
			}
		}
		return $result;
	}

	/**
	 * @return list<string>
	 */
	public static function getCommandNamesForWorld(World $world) : array{
		$result = [];
		foreach(self::getStructuresForWorld($world) as $type){
			$result[] = $type->value;
		}
		return array_values(array_unique($result));
	}

	public static function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ, int $dimensionId, int $seed) : void{
		foreach(StructureType::cases() as $type){
			if($type->getDimensionId() !== $dimensionId || !self::isStartChunk($type, $seed, $chunkX, $chunkZ)){
				continue;
			}
			if(!self::isValidCandidate($type, $chunkX, $chunkZ)){
				continue;
			}

			match($type){
				StructureType::NETHER_FORTRESS => self::generateFortress($world, $chunkX, $chunkZ),
				StructureType::BASTION_REMNANT => self::generateBastion($world, $chunkX, $chunkZ),
				StructureType::END_CITY => self::generateEndCity($world, $chunkX, $chunkZ),
			};
		}
	}

	public static function locateNearest(World $world, StructureType $type, int $originX, int $originZ, int $maxRegionRadius = 128) : ?LocatedStructure{
		if($type->getDimensionId() !== $world->getDimensionId()){
			return null;
		}

		$originChunkX = self::floorDiv($originX, Chunk::EDGE_LENGTH);
		$originChunkZ = self::floorDiv($originZ, Chunk::EDGE_LENGTH);
		$originRegionX = self::floorDiv($originChunkX, $type->getSpacing());
		$originRegionZ = self::floorDiv($originChunkZ, $type->getSpacing());

		$best = null;
		$bestDistance = null;
		for($radius = 0; $radius <= $maxRegionRadius; ++$radius){
			for($regionX = $originRegionX - $radius; $regionX <= $originRegionX + $radius; ++$regionX){
				for($regionZ = $originRegionZ - $radius; $regionZ <= $originRegionZ + $radius; ++$regionZ){
					if($radius !== 0 && abs($regionX - $originRegionX) !== $radius && abs($regionZ - $originRegionZ) !== $radius){
						continue;
					}

					[$chunkX, $chunkZ] = self::getStartChunk($type, $world->getSeed(), $regionX, $regionZ);
					if(!self::isValidCandidate($type, $chunkX, $chunkZ)){
						continue;
					}

					$x = ($chunkX << Chunk::COORD_BIT_SIZE) + 8;
					$z = ($chunkZ << Chunk::COORD_BIT_SIZE) + 8;
					$distance = (($x - $originX) ** 2) + (($z - $originZ) ** 2);
					if($bestDistance === null || $distance < $bestDistance){
						$bestDistance = $distance;
						$best = new LocatedStructure($type, $chunkX, $chunkZ, $x, $z);
					}
				}
			}
		}

		return $best;
	}

	private static function generateFortress(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$centerX = ($chunkX << Chunk::COORD_BIT_SIZE) + 8;
		$centerZ = ($chunkZ << Chunk::COORD_BIT_SIZE) + 8;
		$surfaceY = self::findSurfaceY($world, $centerX, $centerZ, 30, 110);
		if($surfaceY === null){
			return;
		}

		$y = max(46, $surfaceY + 4);
		$brick = VanillaBlocks::NETHER_BRICKS();
		$fence = VanillaBlocks::NETHER_BRICK_FENCE();
		$air = VanillaBlocks::AIR();

		self::fillBox($world, $centerX - 4, $y, $centerZ - 4, $centerX + 4, $y, $centerZ + 4, $brick);
		self::outlineBox($world, $centerX - 4, $y + 1, $centerZ - 4, $centerX + 4, $y + 4, $centerZ + 4, $brick);
		self::fillBox($world, $centerX - 14, $y, $centerZ - 1, $centerX + 14, $y, $centerZ + 1, $brick);
		self::fillBox($world, $centerX - 1, $y, $centerZ - 14, $centerX + 1, $y, $centerZ + 14, $brick);

		self::fillBox($world, $centerX - 2, $y + 1, $centerZ - 2, $centerX + 2, $y + 3, $centerZ + 2, $air);
		self::placeRailings($world, $centerX - 14, $centerX + 14, $y + 1, $centerZ - 2, $centerZ + 2, $fence);
		self::placeRailings($world, $centerZ - 14, $centerZ + 14, $y + 1, $centerX - 2, $centerX + 2, $fence, vertical: true);

		foreach([[-4, -4], [-4, 4], [4, -4], [4, 4], [-14, 0], [14, 0], [0, -14], [0, 14]] as [$dx, $dz]){
			self::buildSupport($world, $centerX + $dx, $y - 1, $centerZ + $dz, $brick);
		}
	}

	private static function generateBastion(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$centerX = ($chunkX << Chunk::COORD_BIT_SIZE) + 8;
		$centerZ = ($chunkZ << Chunk::COORD_BIT_SIZE) + 8;
		$surfaceY = self::findSurfaceY($world, $centerX, $centerZ, 30, 110);
		if($surfaceY === null){
			return;
		}

		$y = max(42, $surfaceY + 2);
		$blackstone = VanillaBlocks::BLACKSTONE();
		$bricks = VanillaBlocks::POLISHED_BLACKSTONE_BRICKS();
		$gilded = VanillaBlocks::GILDED_BLACKSTONE();
		$gold = VanillaBlocks::GOLD();
		$air = VanillaBlocks::AIR();

		self::fillBox($world, $centerX - 6, $y, $centerZ - 6, $centerX + 6, $y + 1, $centerZ + 6, $blackstone);
		self::outlineBox($world, $centerX - 6, $y + 2, $centerZ - 6, $centerX + 6, $y + 8, $centerZ + 6, $bricks);
		self::fillBox($world, $centerX - 4, $y + 2, $centerZ - 4, $centerX + 4, $y + 7, $centerZ + 4, $air);
		self::fillBox($world, $centerX - 1, $y + 2, $centerZ - 1, $centerX + 1, $y + 3, $centerZ + 1, $gold);

		foreach([[-6, -6], [-6, 6], [6, -6], [6, 6]] as [$dx, $dz]){
			self::fillBox($world, $centerX + $dx - 1, $y + 2, $centerZ + $dz - 1, $centerX + $dx + 1, $y + 10, $centerZ + $dz + 1, $blackstone);
			self::buildSupport($world, $centerX + $dx, $y - 1, $centerZ + $dz, $blackstone);
		}

		self::fillBox($world, $centerX - 1, $y + 2, $centerZ - 6, $centerX + 1, $y + 4, $centerZ - 6, $air);
		foreach([[-4, 0], [4, 0], [0, -4], [0, 4]] as [$dx, $dz]){
			self::setBlock($world, $centerX + $dx, $y + 5, $centerZ + $dz, $gilded);
		}
	}

	private static function generateEndCity(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$centerX = ($chunkX << Chunk::COORD_BIT_SIZE) + 8;
		$centerZ = ($chunkZ << Chunk::COORD_BIT_SIZE) + 8;
		$surfaceY = self::findSurfaceY($world, $centerX, $centerZ, 20, 120);
		if($surfaceY === null){
			return;
		}

		$y = $surfaceY + 1;
		$endStoneBricks = VanillaBlocks::END_STONE_BRICKS();
		$purpur = VanillaBlocks::PURPUR();
		$pillar = VanillaBlocks::PURPUR_PILLAR();
		$endRod = VanillaBlocks::END_ROD();
		$air = VanillaBlocks::AIR();

		self::fillBox($world, $centerX - 4, $y, $centerZ - 4, $centerX + 4, $y + 1, $centerZ + 4, $endStoneBricks);
		self::outlineBox($world, $centerX - 2, $y + 2, $centerZ - 2, $centerX + 2, $y + 10, $centerZ + 2, $purpur);
		self::fillBox($world, $centerX - 1, $y + 2, $centerZ - 1, $centerX + 1, $y + 9, $centerZ + 1, $air);
		self::fillBox($world, $centerX - 2, $y + 6, $centerZ - 2, $centerX + 2, $y + 6, $centerZ + 2, $purpur);
		self::fillBox($world, $centerX - 3, $y + 11, $centerZ - 3, $centerX + 3, $y + 11, $centerZ + 3, $purpur);

		foreach([[-2, -2], [-2, 2], [2, -2], [2, 2]] as [$dx, $dz]){
			self::setBlock($world, $centerX + $dx, $y + 12, $centerZ + $dz, $endRod);
		}
		foreach([[-2, -2], [-2, 2], [2, -2], [2, 2]] as [$dx, $dz]){
			self::setBlock($world, $centerX + $dx, $y + 2, $centerZ + $dz, $pillar);
		}

		self::fillBox($world, $centerX + 3, $y + 6, $centerZ - 1, $centerX + 8, $y + 6, $centerZ + 1, $purpur);
		self::outlineBox($world, $centerX + 8, $y + 5, $centerZ - 2, $centerX + 12, $y + 9, $centerZ + 2, $purpur);
		self::fillBox($world, $centerX + 9, $y + 5, $centerZ - 1, $centerX + 11, $y + 8, $centerZ + 1, $air);
		self::setBlock($world, $centerX + 12, $y + 10, $centerZ, $endRod);

		foreach([[-4, -4], [-4, 4], [4, -4], [4, 4], [12, 0]] as [$dx, $dz]){
			self::buildSupport($world, $centerX + $dx, $y - 1, $centerZ + $dz, $endStoneBricks);
		}
	}

	private static function placeRailings(ChunkManager $world, int $minPrimary, int $maxPrimary, int $y, int $minSecondary, int $maxSecondary, Block $block, bool $vertical = false) : void{
		for($primary = $minPrimary; $primary <= $maxPrimary; ++$primary){
			foreach([$minSecondary, $maxSecondary] as $secondary){
				if($vertical){
					self::setBlock($world, $secondary, $y, $primary, $block);
				}else{
					self::setBlock($world, $primary, $y, $secondary, $block);
				}
			}
		}
	}

	private static function buildSupport(ChunkManager $world, int $x, int $startY, int $z, Block $block) : void{
		for($y = $startY; $y > $world->getMinY(); --$y){
			if(!$world->getBlockAt($x, $y, $z)->canBeReplaced()){
				break;
			}
			self::setBlock($world, $x, $y, $z, $block);
		}
	}

	private static function fillBox(ChunkManager $world, int $minX, int $minY, int $minZ, int $maxX, int $maxY, int $maxZ, Block $block) : void{
		for($x = $minX; $x <= $maxX; ++$x){
			for($y = $minY; $y <= $maxY; ++$y){
				for($z = $minZ; $z <= $maxZ; ++$z){
					self::setBlock($world, $x, $y, $z, $block);
				}
			}
		}
	}

	private static function outlineBox(ChunkManager $world, int $minX, int $minY, int $minZ, int $maxX, int $maxY, int $maxZ, Block $block) : void{
		for($x = $minX; $x <= $maxX; ++$x){
			for($y = $minY; $y <= $maxY; ++$y){
				for($z = $minZ; $z <= $maxZ; ++$z){
					if($x !== $minX && $x !== $maxX && $y !== $minY && $y !== $maxY && $z !== $minZ && $z !== $maxZ){
						continue;
					}
					self::setBlock($world, $x, $y, $z, $block);
				}
			}
		}
	}

	private static function setBlock(ChunkManager $world, int $x, int $y, int $z, Block $block) : void{
		if($world->isInWorld($x, $y, $z)){
			$world->setBlockAt($x, $y, $z, clone $block);
		}
	}

	private static function findSurfaceY(ChunkManager $world, int $x, int $z, int $minY, int $maxY) : ?int{
		for($y = min($maxY, $world->getMaxY() - 2); $y >= max($minY, $world->getMinY() + 2); --$y){
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

	private static function isStartChunk(StructureType $type, int $seed, int $chunkX, int $chunkZ) : bool{
		$regionX = self::floorDiv($chunkX, $type->getSpacing());
		$regionZ = self::floorDiv($chunkZ, $type->getSpacing());
		[$candidateX, $candidateZ] = self::getStartChunk($type, $seed, $regionX, $regionZ);

		return $chunkX === $candidateX && $chunkZ === $candidateZ;
	}

	/**
	 * @return array{int, int}
	 */
	private static function getStartChunk(StructureType $type, int $seed, int $regionX, int $regionZ) : array{
		$spacing = $type->getSpacing();
		$range = max(1, $spacing - $type->getSeparation());
		$baseChunkX = $regionX * $spacing;
		$baseChunkZ = $regionZ * $spacing;
		$offsetX = self::positiveMod(crc32($seed . ":" . $type->getSalt() . ":" . $regionX . ":x:" . $regionZ), $range);
		$offsetZ = self::positiveMod(crc32($seed . ":" . $type->getSalt() . ":" . $regionX . ":z:" . $regionZ), $range);

		return [$baseChunkX + $offsetX, $baseChunkZ + $offsetZ];
	}

	private static function isValidCandidate(StructureType $type, int $chunkX, int $chunkZ) : bool{
		return match($type){
			StructureType::END_CITY => ((($chunkX - (End::CENTER_X >> Chunk::COORD_BIT_SIZE)) ** 2) + (($chunkZ - (End::CENTER_Z >> Chunk::COORD_BIT_SIZE)) ** 2)) >= (20 * 20),
			default => true,
		};
	}

	private static function floorDiv(int $value, int $divisor) : int{
		if($value >= 0){
			return intdiv($value, $divisor);
		}

		return -intdiv(-$value + $divisor - 1, $divisor);
	}

	private static function positiveMod(int $value, int $modulus) : int{
		$result = $value % $modulus;
		return $result < 0 ? $result + $modulus : $result;
	}
}
