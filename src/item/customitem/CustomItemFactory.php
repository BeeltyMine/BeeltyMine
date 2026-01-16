<?php

declare(strict_types=1);

namespace pocketmine\item\customitem;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\SingletonTrait;

/**
 * Factory for creating custom item instances.
 */
final class CustomItemFactory{
	use SingletonTrait;
	
	/**
	 * Creates a custom item instance from a definition.
	 */
	public function create(CustomItemDefinition $definition, int $count = 1) : CustomItem{
		$item = new CustomItem(
			new ItemIdentifier($definition->getNumericId()),
			$definition->getDisplayName(),
			$definition
		);
		$item->setCount($count);
		return $item;
	}
	
	/**
	 * Creates a custom item by identifier.
	 * 
	 * @throws \InvalidArgumentException if the item is not registered
	 */
	public function createByIdentifier(string $identifier, int $count = 1) : CustomItem{
		$definition = CustomItemManager::getInstance()->getByIdentifier($identifier);
		if($definition === null){
			throw new \InvalidArgumentException("Custom item with identifier $identifier is not registered");
		}
		return $this->create($definition, $count);
	}
	
	/**
	 * Creates a custom item by numeric ID.
	 * 
	 * @throws \InvalidArgumentException if the item is not registered
	 */
	public function createById(int $id, int $count = 1) : CustomItem{
		$definition = CustomItemManager::getInstance()->get($id);
		if($definition === null){
			throw new \InvalidArgumentException("Custom item with ID $id is not registered");
		}
		return $this->create($definition, $count);
	}
	
	/**
	 * Registers a custom item to the string parser for /give command support.
	 */
	public function registerToStringParser(string $alias, CustomItemDefinition $definition) : void{
		StringToItemParser::getInstance()->register($alias, fn() => $this->create($definition));
	}
}
