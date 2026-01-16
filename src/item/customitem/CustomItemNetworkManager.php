<?php

declare(strict_types=1);

namespace pocketmine\item\customitem;

use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\nbt\tag\CompoundTag;
use ReflectionClass;
use function array_merge;

/**
 * Manages network registration for custom items.
 * Modifies ItemTypeDictionary to include custom item mappings.
 */
final class CustomItemNetworkManager{
	
	private static ?self $instance = null;
	
	/** @var ItemTypeEntry[] */
	private array $customItemEntries = [];
	
	private bool $registered = false;
	
	private function __construct(){}
	
	public static function getInstance() : self{
		if(self::$instance === null){
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Registers a custom item to the network layer.
	 * Must be called before ItemTypeDictionary is used.
	 */
	public function registerCustomItem(CustomItemDefinition $definition) : void{
		$this->customItemEntries[] = new ItemTypeEntry(
			$definition->getIdentifier(),
			$definition->getNumericId(),
			false, // componentBased
			0,     // version
			new CacheableNbt(CompoundTag::create()) // empty NBT
		);
	}
	
	/**
	 * Applies custom item mappings to an ItemTypeDictionary.
	 * This uses reflection to modify the internal maps.
	 * 
	 * @param ItemTypeDictionary $dictionary The dictionary to modify
	 */
	public function applyToItemTypeDictionary(ItemTypeDictionary $dictionary) : void{
		if($this->registered || empty($this->customItemEntries)){
			return;
		}
		
		try{
			$reflection = new ReflectionClass($dictionary);
			
			// Get private properties
			$stringToIntProperty = $reflection->getProperty("stringToIntMap");
			$stringToIntProperty->setAccessible(true);
			$stringToIntMap = $stringToIntProperty->getValue($dictionary);
			
			$intToStringProperty = $reflection->getProperty("intToStringIdMap");
			$intToStringProperty->setAccessible(true);
			$intToStringIdMap = $intToStringProperty->getValue($dictionary);
			
			// Add custom item mappings
			foreach($this->customItemEntries as $entry){
				$stringToIntMap[$entry->getStringId()] = $entry->getNumericId();
				$intToStringIdMap[$entry->getNumericId()] = $entry->getStringId();
			}
			
			// Set modified maps back
			$stringToIntProperty->setValue($dictionary, $stringToIntMap);
			$intToStringProperty->setValue($dictionary, $intToStringIdMap);
			
			// Also modify the entries array if needed
			$entriesProperty = $reflection->getProperty("itemTypes");
			$entriesProperty->setAccessible(true);
			$entries = $entriesProperty->getValue($dictionary);
			$entriesProperty->setValue($dictionary, array_merge($entries, $this->customItemEntries));
			
			$this->registered = true;
		}catch(\ReflectionException $e){
			throw new \RuntimeException("Failed to register custom items to ItemTypeDictionary: " . $e->getMessage(), 0, $e);
		}
	}
	
	/**
	 * Gets all custom item type entries for packet transmission.
	 * 
	 * @return ItemTypeEntry[]
	 */
	public function getCustomItemEntries() : array{
		return $this->customItemEntries;
	}
	
	/**
	 * Checks if custom items have been registered to the network layer.
	 */
	public function isRegistered() : bool{
		return $this->registered;
	}
}
