<?php

declare(strict_types=1);

namespace pocketmine\item\customitem;

use pocketmine\item\customitem\CustomItemDefinition;
use pocketmine\utils\SingletonTrait;

/**
 * Manages registration and retrieval of custom items.
 */
final class CustomItemManager{
	use SingletonTrait;
	
	/** @var CustomItemDefinition[] */
	private array $customItems = [];
	
	/** @var array<string, CustomItemDefinition> */
	private array $customItemsByIdentifier = [];
	
	/**
	 * Registers a custom item definition.
	 * 
	 * @throws \InvalidArgumentException if the item is already registered
	 */
	public function register(CustomItemDefinition $definition) : void{
		$id = $definition->getNumericId();
		$identifier = $definition->getIdentifier();
		
		if(isset($this->customItems[$id])){
			throw new \InvalidArgumentException("Custom item with numeric ID $id is already registered");
		}
		
		if(isset($this->customItemsByIdentifier[$identifier])){
			throw new \InvalidArgumentException("Custom item with identifier $identifier is already registered");
		}
		
		$this->customItems[$id] = $definition;
		$this->customItemsByIdentifier[$identifier] = $definition;
	}
	
	/**
	 * Gets a custom item definition by numeric ID.
	 */
	public function get(int $id) : ?CustomItemDefinition{
		return $this->customItems[$id] ?? null;
	}
	
	/**
	 * Gets a custom item definition by identifier.
	 */
	public function getByIdentifier(string $identifier) : ?CustomItemDefinition{
		return $this->customItemsByIdentifier[$identifier] ?? null;
	}
	
	/**
	 * Returns all registered custom item definitions.
	 * 
	 * @return CustomItemDefinition[]
	 */
	public function getAll() : array{
		return $this->customItems;
	}
	
	/**
	 * Returns all registered custom items as client-ready data.
	 * 
	 * @return array[]
	 */
	public function getAllAsData() : array{
		$data = [];
		foreach($this->customItems as $definition){
			$data[] = $definition->toData();
		}
		return $data;
	}
	
	/**
	 * Checks if a custom item with the given numeric ID is registered.
	 */
	public function isRegistered(int $id) : bool{
		return isset($this->customItems[$id]);
	}
	
	/**
	 * Checks if a custom item with the given identifier is registered.
	 */
	public function isRegisteredByIdentifier(string $identifier) : bool{
		return isset($this->customItemsByIdentifier[$identifier]);
	}
	
	/**
	 * Unregisters all custom items. Use with caution!
	 */
	public function reset() : void{
		$this->customItems = [];
		$this->customItemsByIdentifier = [];
	}
}
