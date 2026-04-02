<?php

declare(strict_types=1);

namespace pocketmine\data\bedrock;

use pocketmine\item\ArmorTrimMaterial;
use pocketmine\item\Item;
use pocketmine\item\VanillaArmorTrimMaterials as Materials;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\SingletonTrait;
use function array_key_exists;
use function array_values;
use function spl_object_id;

final class ArmorTrimMaterialTypeIdMap{
	use SingletonTrait;

	/**
	 * @var ArmorTrimMaterial[]
	 * @phpstan-var array<string, ArmorTrimMaterial>
	 */
	private array $idToMaterial = [];
	/**
	 * @var ArmorTrimMaterial[]
	 * @phpstan-var array<int, ArmorTrimMaterial>
	 */
	private array $itemToMaterial = [];
	/**
	 * @var string[]
	 * @phpstan-var array<int, string>
	 */
	private array $materialToId = [];

	public function __construct(){
		foreach(Materials::getAll() as $material){
			$this->register(match($material){
				Materials::AMETHYST() => ArmorTrimMaterialTypeIds::AMETHYST,
				Materials::COPPER() => ArmorTrimMaterialTypeIds::COPPER,
				Materials::DIAMOND() => ArmorTrimMaterialTypeIds::DIAMOND,
				Materials::EMERALD() => ArmorTrimMaterialTypeIds::EMERALD,
				Materials::GOLD() => ArmorTrimMaterialTypeIds::GOLD,
				Materials::IRON() => ArmorTrimMaterialTypeIds::IRON,
				Materials::LAPIS() => ArmorTrimMaterialTypeIds::LAPIS,
				Materials::NETHERITE() => ArmorTrimMaterialTypeIds::NETHERITE,
				Materials::QUARTZ() => ArmorTrimMaterialTypeIds::QUARTZ,
				Materials::REDSTONE() => ArmorTrimMaterialTypeIds::REDSTONE,
				Materials::RESIN() => ArmorTrimMaterialTypeIds::RESIN,
				default => throw new AssumptionFailedError('Unhandled armor trim material type')
			}, $material);
		}
	}

	public function register(string $stringId, ArmorTrimMaterial $material) : void{
		$this->idToMaterial[$stringId] = $material;
		$this->itemToMaterial[$material->getItem()->getStateId()] = $material;
		$this->materialToId[spl_object_id($material)] = $stringId;
	}

	public function fromId(string $id) : ?ArmorTrimMaterial{
		return $this->idToMaterial[$id] ?? null;
	}

	public function fromItem(Item $item) : ?ArmorTrimMaterial{
		return $this->itemToMaterial[$item->getStateId()] ?? null;
	}

	public function toId(ArmorTrimMaterial $material) : string{
		$key = spl_object_id($material);
		if(!array_key_exists($key, $this->materialToId)){
			throw new \InvalidArgumentException('Missing mapping for armor trim material with item ' . $material->getItem()->getName() . ' and color ' . $material->getColor());
		}
		return $this->materialToId[$key];
	}

	/**
	 * @return ArmorTrimMaterial[]
	 * @phpstan-return list<ArmorTrimMaterial>
	 */
	public function getAllMaterials() : array{
		return array_values($this->idToMaterial);
	}
}