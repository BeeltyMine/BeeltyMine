<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\utils\RegistryTrait;

/**
 * @method static ArmorTrimPattern COAST()
 * @method static ArmorTrimPattern DUNE()
 * @method static ArmorTrimPattern EYE()
 * @method static ArmorTrimPattern HOST()
 * @method static ArmorTrimPattern RAISER()
 * @method static ArmorTrimPattern RIB()
 * @method static ArmorTrimPattern SENTRY()
 * @method static ArmorTrimPattern SHAPER()
 * @method static ArmorTrimPattern SILENCE()
 * @method static ArmorTrimPattern SNOUT()
 * @method static ArmorTrimPattern SPIRE()
 * @method static ArmorTrimPattern TIDE()
 * @method static ArmorTrimPattern VEX()
 * @method static ArmorTrimPattern WARD()
 * @method static ArmorTrimPattern WAYFINDER()
 * @method static ArmorTrimPattern WILD()
 */
final class VanillaArmorTrimPatterns{
	use RegistryTrait;

	private function __construct(){
		// NOOP
	}

	protected static function register(string $name, ArmorTrimPattern $armorPattern) : void{
		self::_registryRegister($name, $armorPattern);
	}

	/**
	 * @return ArmorTrimPattern[]
	 * @phpstan-return array<string, ArmorTrimPattern>
	 */
	public static function getAll() : array{
		/** @var ArmorTrimPattern[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		self::register('coast', new ArmorTrimPattern(VanillaItems::COAST_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('dune', new ArmorTrimPattern(VanillaItems::DUNE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('eye', new ArmorTrimPattern(VanillaItems::EYE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('host', new ArmorTrimPattern(VanillaItems::HOST_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('raiser', new ArmorTrimPattern(VanillaItems::RAISER_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('rib', new ArmorTrimPattern(VanillaItems::RIB_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('sentry', new ArmorTrimPattern(VanillaItems::SENTRY_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('shaper', new ArmorTrimPattern(VanillaItems::SHAPER_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('silence', new ArmorTrimPattern(VanillaItems::SILENCE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('snout', new ArmorTrimPattern(VanillaItems::SNOUT_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('spire', new ArmorTrimPattern(VanillaItems::SPIRE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('tide', new ArmorTrimPattern(VanillaItems::TIDE_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('vex', new ArmorTrimPattern(VanillaItems::VEX_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('ward', new ArmorTrimPattern(VanillaItems::WARD_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('wayfinder', new ArmorTrimPattern(VanillaItems::WAYFINDER_ARMOR_TRIM_SMITHING_TEMPLATE()));
		self::register('wild', new ArmorTrimPattern(VanillaItems::WILD_ARMOR_TRIM_SMITHING_TEMPLATE()));
	}
}