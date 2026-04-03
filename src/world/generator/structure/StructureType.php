<?php

declare(strict_types=1);

namespace pocketmine\world\generator\structure;

use pocketmine\network\mcpe\protocol\types\DimensionIds;
use function strtolower;
use function str_replace;

enum StructureType : string{
	case NETHER_FORTRESS = "fortress";
	case BASTION_REMNANT = "bastion";
	case END_CITY = "endcity";

	public static function fromString(string $value) : ?self{
		$normalized = strtolower(str_replace([" ", "_", "-"], "", $value));
		foreach(self::cases() as $case){
			foreach($case->getAliases() as $alias){
				if($normalized === $alias){
					return $case;
				}
			}
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	public static function getAllCommandNames() : array{
		$names = [];
		foreach(self::cases() as $case){
			$names[] = $case->value;
		}
		return $names;
	}

	public function getDisplayName() : string{
		return match($this){
			self::NETHER_FORTRESS => "Nether Fortress",
			self::BASTION_REMNANT => "Bastion Remnant",
			self::END_CITY => "End City",
		};
	}

	/**
	 * @return list<string>
	 */
	public function getAliases() : array{
		return match($this){
			self::NETHER_FORTRESS => ["fortress", "netherfortress"],
			self::BASTION_REMNANT => ["bastion", "bastionremnant"],
			self::END_CITY => ["endcity", "end_city"],
		};
	}

	/**
	 * @phpstan-return DimensionIds::*
	 */
	public function getDimensionId() : int{
		return match($this){
			self::NETHER_FORTRESS, self::BASTION_REMNANT => DimensionIds::NETHER,
			self::END_CITY => DimensionIds::THE_END,
		};
	}

	public function getSpacing() : int{
		return match($this){
			self::NETHER_FORTRESS => 24,
			self::BASTION_REMNANT => 32,
			self::END_CITY => 40,
		};
	}

	public function getSeparation() : int{
		return match($this){
			self::NETHER_FORTRESS => 8,
			self::BASTION_REMNANT => 10,
			self::END_CITY => 12,
		};
	}

	public function getSalt() : int{
		return match($this){
			self::NETHER_FORTRESS => 30084232,
			self::BASTION_REMNANT => 84232011,
			self::END_CITY => 10387313,
		};
	}
}
