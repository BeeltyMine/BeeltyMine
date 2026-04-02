<?php

declare(strict_types=1);

namespace pocketmine\data\bedrock;

use pocketmine\item\ArmorTrimPattern;
use pocketmine\item\Item;
use pocketmine\item\VanillaArmorTrimPatterns as Patterns;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\SingletonTrait;
use function array_key_exists;
use function array_values;
use function spl_object_id;

final class ArmorTrimPatternTypeIdMap{
	use SingletonTrait;

	/**
	 * @var ArmorTrimPattern[]
	 * @phpstan-var array<string, ArmorTrimPattern>
	 */
	private array $idToPattern = [];
	/**
	 * @var ArmorTrimPattern[]
	 * @phpstan-var array<int, ArmorTrimPattern>
	 */
	private array $itemToPattern = [];
	/**
	 * @var string[]
	 * @phpstan-var array<int, string>
	 */
	private array $patternToId = [];

	public function __construct(){
		foreach(Patterns::getAll() as $pattern){
			$this->register(match($pattern){
				Patterns::COAST() => ArmorTrimPatternTypeIds::COAST,
				Patterns::DUNE() => ArmorTrimPatternTypeIds::DUNE,
				Patterns::EYE() => ArmorTrimPatternTypeIds::EYE,
				Patterns::HOST() => ArmorTrimPatternTypeIds::HOST,
				Patterns::RAISER() => ArmorTrimPatternTypeIds::RAISER,
				Patterns::RIB() => ArmorTrimPatternTypeIds::RIB,
				Patterns::SENTRY() => ArmorTrimPatternTypeIds::SENTRY,
				Patterns::SHAPER() => ArmorTrimPatternTypeIds::SHAPER,
				Patterns::SILENCE() => ArmorTrimPatternTypeIds::SILENCE,
				Patterns::SNOUT() => ArmorTrimPatternTypeIds::SNOUT,
				Patterns::SPIRE() => ArmorTrimPatternTypeIds::SPIRE,
				Patterns::TIDE() => ArmorTrimPatternTypeIds::TIDE,
				Patterns::VEX() => ArmorTrimPatternTypeIds::VEX,
				Patterns::WARD() => ArmorTrimPatternTypeIds::WARD,
				Patterns::WAYFINDER() => ArmorTrimPatternTypeIds::WAYFINDER,
				Patterns::WILD() => ArmorTrimPatternTypeIds::WILD,
				default => throw new AssumptionFailedError('Unhandled armor trim pattern type')
			}, $pattern);
		}
	}

	public function register(string $stringId, ArmorTrimPattern $pattern) : void{
		$this->idToPattern[$stringId] = $pattern;
		$this->itemToPattern[$pattern->getItem()->getStateId()] = $pattern;
		$this->patternToId[spl_object_id($pattern)] = $stringId;
	}

	public function fromId(string $id) : ?ArmorTrimPattern{
		return $this->idToPattern[$id] ?? null;
	}

	public function fromItem(Item $item) : ?ArmorTrimPattern{
		return $this->itemToPattern[$item->getStateId()] ?? null;
	}

	public function toId(ArmorTrimPattern $pattern) : string{
		$key = spl_object_id($pattern);
		if(!array_key_exists($key, $this->patternToId)){
			throw new \InvalidArgumentException('Missing mapping for armor trim pattern with item ' . $pattern->getItem()->getName());
		}
		return $this->patternToId[$key];
	}

	/**
	 * @return ArmorTrimPattern[]
	 * @phpstan-return list<ArmorTrimPattern>
	 */
	public function getAllPatterns() : array{
		return array_values($this->idToPattern);
	}
}