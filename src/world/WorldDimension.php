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

namespace pocketmine\world;

use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\GeneratorType;
use function strtolower;

final class WorldDimension{

	private function __construct(){
		//NOOP
	}

	/**
	 * @phpstan-return DimensionIds::*
	 */
	public static function resolveDimensionId(string $generatorName, ?string $configuredDimension = null) : int{
		$resolved = $configuredDimension !== null ? self::parseDimensionString($configuredDimension) : null;
		if($resolved !== null){
			return $resolved;
		}

		return self::generatorNameToDimensionId($generatorName);
	}

	/**
	 * @phpstan-return DimensionIds::*|null
	 */
	public static function parseDimensionString(string $dimensionName) : ?int{
		return match(strtolower($dimensionName)){
			"overworld", "default", "normal" => DimensionIds::OVERWORLD,
			"nether", "hell" => DimensionIds::NETHER,
			"end", "the_end" => DimensionIds::THE_END,
			default => null,
		};
	}

	/**
	 * @phpstan-return DimensionIds::*
	 */
	public static function generatorNameToDimensionId(string $generatorName) : int{
		return match(strtolower($generatorName)){
			"nether", "hell" => DimensionIds::NETHER,
			"end", "the_end" => DimensionIds::THE_END,
			default => DimensionIds::OVERWORLD,
		};
	}

	public static function resolveGeneratorType(string $generatorName, int $dimensionId) : int{
		return match($dimensionId){
			DimensionIds::NETHER => GeneratorType::NETHER,
			DimensionIds::THE_END => GeneratorType::THE_END,
			default => strtolower($generatorName) === "flat" ? GeneratorType::FLAT : GeneratorType::OVERWORLD,
		};
	}

	public static function dimensionIdToString(int $dimensionId) : string{
		return match($dimensionId){
			DimensionIds::NETHER => "nether",
			DimensionIds::THE_END => "end",
			default => "overworld",
		};
	}

	public static function hasWeather(int $dimensionId) : bool{
		return $dimensionId === DimensionIds::OVERWORLD;
	}
}
