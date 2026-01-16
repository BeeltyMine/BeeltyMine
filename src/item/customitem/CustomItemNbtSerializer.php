<?php

declare(strict_types=1);

namespace pocketmine\item\customitem;

use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;

/**
 * Handles NBT serialization and deserialization for custom items.
 * This allows custom items to be saved to disk and loaded back.
 */
final class CustomItemNbtSerializer{
	
	private const TAG_CUSTOM_ITEM = "CustomItem";
	private const TAG_IDENTIFIER = "Identifier";
	private const TAG_NUMERIC_ID = "NumericId";
	
	/**
	 * Saves custom item data to NBT.
	 * 
	 * @param CustomItem $item The custom item to serialize
	 * @param CompoundTag $nbt The NBT tag to save to
	 */
	public static function serializeToNbt(CustomItem $item, CompoundTag $nbt) : void{
		$customData = CompoundTag::create()
			->setString(self::TAG_IDENTIFIER, $item->getDefinition()->getIdentifier())
			->setInt(self::TAG_NUMERIC_ID, $item->getDefinition()->getNumericId());
		
		$nbt->setTag(self::TAG_CUSTOM_ITEM, $customData);
	}
	
	/**
	 * Loads custom item data from NBT.
	 * 
	 * @param CompoundTag $nbt The NBT tag to load from
	 * @return array{identifier: string, numericId: int}|null Returns item data or null if not a custom item
	 */
	public static function deserializeFromNbt(CompoundTag $nbt) : ?array{
		$customData = $nbt->getCompoundTag(self::TAG_CUSTOM_ITEM);
		if($customData === null){
			return null;
		}
		
		$identifier = $customData->getString(self::TAG_IDENTIFIER, "");
		$numericId = $customData->getInt(self::TAG_NUMERIC_ID, 0);
		
		if($identifier === "" || $numericId === 0){
			return null;
		}
		
		return [
			"identifier" => $identifier,
			"numericId" => $numericId
		];
	}
	
	/**
	 * Checks if an NBT tag contains custom item data.
	 * 
	 * @param CompoundTag $nbt The NBT tag to check
	 * @return bool True if the tag contains custom item data
	 */
	public static function isCustomItem(CompoundTag $nbt) : bool{
		return $nbt->getCompoundTag(self::TAG_CUSTOM_ITEM) !== null;
	}
}
