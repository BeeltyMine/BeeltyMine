<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\utils\RegistryTrait;
use pocketmine\utils\TextFormat;

/**
 * @method static ArmorTrimMaterial AMETHYST()
 * @method static ArmorTrimMaterial COPPER()
 * @method static ArmorTrimMaterial DIAMOND()
 * @method static ArmorTrimMaterial EMERALD()
 * @method static ArmorTrimMaterial GOLD()
 * @method static ArmorTrimMaterial IRON()
 * @method static ArmorTrimMaterial LAPIS()
 * @method static ArmorTrimMaterial NETHERITE()
 * @method static ArmorTrimMaterial QUARTZ()
 * @method static ArmorTrimMaterial REDSTONE()
 * @method static ArmorTrimMaterial RESIN()
 */
final class VanillaArmorTrimMaterials{
	use RegistryTrait;

	private function __construct(){
		// NOOP
	}

	protected static function register(string $name, ArmorTrimMaterial $armorMaterial) : void{
		self::_registryRegister($name, $armorMaterial);
	}

	/**
	 * @return ArmorTrimMaterial[]
	 * @phpstan-return array<string, ArmorTrimMaterial>
	 */
	public static function getAll() : array{
		/** @var ArmorTrimMaterial[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		self::register('amethyst', new ArmorTrimMaterial(VanillaItems::AMETHYST_SHARD(), TextFormat::MATERIAL_AMETHYST));
		self::register('copper', new ArmorTrimMaterial(VanillaItems::COPPER_INGOT(), TextFormat::MATERIAL_COPPER));
		self::register('diamond', new ArmorTrimMaterial(VanillaItems::DIAMOND(), TextFormat::MATERIAL_DIAMOND));
		self::register('emerald', new ArmorTrimMaterial(VanillaItems::EMERALD(), TextFormat::MATERIAL_EMERALD));
		self::register('gold', new ArmorTrimMaterial(VanillaItems::GOLD_INGOT(), TextFormat::MATERIAL_GOLD));
		self::register('iron', new ArmorTrimMaterial(VanillaItems::IRON_INGOT(), TextFormat::MATERIAL_IRON));
		self::register('lapis', new ArmorTrimMaterial(VanillaItems::LAPIS_LAZULI(), TextFormat::MATERIAL_LAPIS));
		self::register('netherite', new ArmorTrimMaterial(VanillaItems::NETHERITE_INGOT(), TextFormat::MATERIAL_NETHERITE));
		self::register('quartz', new ArmorTrimMaterial(VanillaItems::NETHER_QUARTZ(), TextFormat::MATERIAL_QUARTZ));
		self::register('redstone', new ArmorTrimMaterial(VanillaItems::REDSTONE_DUST(), TextFormat::MATERIAL_REDSTONE));
		self::register('resin', new ArmorTrimMaterial(VanillaItems::RESIN_BRICK(), TextFormat::MATERIAL_RESIN));
	}
}