<?php

declare(strict_types=1);

namespace pocketmine\item\customitem;

use pocketmine\data\bedrock\item\ItemDeserializer;
use pocketmine\data\bedrock\item\ItemSerializer;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;

/**
 * Handles registration of custom items to the server's serialization systems.
 * This makes custom items work like vanilla items - they can be saved, loaded, and sent over network.
 */
final class CustomItemRegistrar{
	
	/**
	 * Registers a custom item to all necessary systems:
	 * - ItemSerializer (for network/NBT encoding)
	 * - ItemDeserializer (for network/NBT decoding)
	 * - StringToItemParser (for /give command)
	 * - CustomItemManager (for internal tracking)
	 * - CustomItemNetworkManager (for network layer registration)
	 * 
	 * @param CustomItemDefinition $definition The custom item definition to register
	 * @param ItemSerializer $serializer The item serializer instance
	 * @param ItemDeserializer $deserializer The item deserializer instance
	 * @param string|null $commandAlias Optional alias for /give command (default: uses identifier)
	 */
	public static function register(
		CustomItemDefinition $definition,
		ItemSerializer $serializer,
		ItemDeserializer $deserializer,
		?string $commandAlias = null
	) : void{
		$identifier = $definition->getIdentifier();
		$numericId = $definition->getNumericId();
		
		// 1. Register to CustomItemManager
		CustomItemManager::getInstance()->register($definition);
		
		// 2. Register serializer (Item -> SavedItemData)
		$serializer->map(
			new CustomItem(
				new ItemIdentifier($numericId),
				$definition->getDisplayName(),
				$definition
			),
			fn(Item $item) => new SavedItemData($identifier, 0)
		);
		
		// 3. Register deserializer (SavedItemData -> Item)
		$deserializer->map(
			$identifier,
			fn(SavedItemData $data) => CustomItemFactory::getInstance()->create($definition, 1)
		);
		
		// 4. Register to StringToItemParser for /give command
		$alias = $commandAlias ?? self::extractShortName($identifier);
		CustomItemFactory::getInstance()->registerToStringParser($alias, $definition);
		
		// 5. Register to network layer (ItemTypeDictionary)
		CustomItemNetworkManager::getInstance()->registerCustomItem($definition);
	}
	
	/**
	 * Registers multiple custom items at once.
	 * 
	 * @param CustomItemDefinition[] $definitions Array of custom item definitions
	 * @param ItemSerializer $serializer The item serializer instance
	 * @param ItemDeserializer $deserializer The item deserializer instance
	 */
	public static function registerMultiple(
		array $definitions,
		ItemSerializer $serializer,
		ItemDeserializer $deserializer
	) : void{
		foreach($definitions as $definition){
			self::register($definition, $serializer, $deserializer);
		}
	}
	
	/**
	 * Registers all custom items from CustomItemManager.
	 * Call this during server initialization.
	 * 
	 * @param ItemSerializer $serializer The item serializer instance
	 * @param ItemDeserializer $deserializer The item deserializer instance
	 */
	public static function registerAll(
		ItemSerializer $serializer,
		ItemDeserializer $deserializer
	) : void{
		$manager = CustomItemManager::getInstance();
		foreach($manager->getAll() as $definition){
			self::register($definition, $serializer, $deserializer);
		}
	}
	
	/**
	 * Extracts the short name from a namespaced identifier.
	 * Example: "myserver:custom_sword" -> "custom_sword"
	 */
	private static function extractShortName(string $identifier) : string{
		$parts = explode(':', $identifier, 2);
		return $parts[1] ?? $parts[0];
	}
}
