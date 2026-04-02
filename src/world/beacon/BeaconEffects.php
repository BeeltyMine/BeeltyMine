<?php

declare(strict_types=1);

namespace pocketmine\world\beacon;

use pocketmine\data\bedrock\EffectIds;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

final class BeaconEffects{
	private function __construct(){
		//NOOP
	}

	public static function isValidPaymentItem(Item $item) : bool{
		static $validTypeIds = null;

		if($validTypeIds === null){
			$validTypeIds = [
				VanillaItems::IRON_INGOT()->getTypeId() => true,
				VanillaItems::GOLD_INGOT()->getTypeId() => true,
				VanillaItems::DIAMOND()->getTypeId() => true,
				VanillaItems::EMERALD()->getTypeId() => true,
				VanillaItems::NETHERITE_INGOT()->getTypeId() => true,
			];
		}

		return isset($validTypeIds[$item->getTypeId()]);
	}

	public static function isPrimaryEffectAllowed(int $level, int $effectId) : bool{
		return $level >= match($effectId){
			EffectIds::SPEED, EffectIds::HASTE => 1,
			EffectIds::RESISTANCE, EffectIds::JUMP_BOOST => 2,
			EffectIds::STRENGTH => 3,
			default => 5,
		};
	}

	public static function isSecondaryEffectAllowed(int $level, int $primaryEffectId, int $secondaryEffectId) : bool{
		if($secondaryEffectId === 0){
			return true;
		}

		if($level < 4 || !self::isPrimaryEffectAllowed($level, $primaryEffectId)){
			return false;
		}

		return $secondaryEffectId === EffectIds::REGENERATION || $secondaryEffectId === $primaryEffectId;
	}
}