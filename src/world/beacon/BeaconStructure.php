<?php

declare(strict_types=1);

namespace pocketmine\world\beacon;

final class BeaconStructure{
	private const MAX_LAYERS = 4;

	/**
	 * @param \Closure(int, int, int) : bool $isLayerBlockValid Receives (layer, offsetX, offsetZ)
	 */
	public static function calculateLevel(\Closure $isLayerBlockValid) : int{
		$level = 0;

		for($layer = 1; $layer <= self::MAX_LAYERS; ++$layer){
			$radius = $layer;
			for($x = -$radius; $x <= $radius; ++$x){
				for($z = -$radius; $z <= $radius; ++$z){
					if(!$isLayerBlockValid($layer, $x, $z)){
						return $level;
					}
				}
			}
			$level = $layer;
		}

		return $level;
	}

	public static function getRangeForLevel(int $level) : int{
		return $level > 0 ? 10 + ($level * 10) : 0;
	}

	public static function getPrimaryAmplifier(int $level, bool $secondaryMatchesPrimary) : int{
		return $level >= 4 && $secondaryMatchesPrimary ? 1 : 0;
	}

	public static function isValidBaseBlockTypeId(int $typeId) : bool{
		static $validTypeIds = null;

		if($validTypeIds === null){
			$validTypeIds = [
				\pocketmine\block\VanillaBlocks::IRON()->getTypeId() => true,
				\pocketmine\block\VanillaBlocks::GOLD()->getTypeId() => true,
				\pocketmine\block\VanillaBlocks::EMERALD()->getTypeId() => true,
				\pocketmine\block\VanillaBlocks::DIAMOND()->getTypeId() => true,
				\pocketmine\block\VanillaBlocks::NETHERITE()->getTypeId() => true,
			];
		}

		return isset($validTypeIds[$typeId]);
	}
}