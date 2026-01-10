<?php

declare(strict_types=1);

namespace pocketmine\item\customitem;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\nbt\tag\CompoundTag;

/**
 * Represents a custom item instance with its definition.
 */
class CustomItem extends Item{
	
	public function __construct(
		ItemIdentifier $identifier,
		string $name,
		private CustomItemDefinition $definition
	){
		parent::__construct($identifier, $name);
	}
	
	public function getDefinition() : CustomItemDefinition{
		return $this->definition;
	}
	
	public function getMaxStackSize() : int{
		$maxStackComponent = $this->definition->getComponent("minecraft:max_stack_size");
		if($maxStackComponent !== null){
			return $maxStackComponent->getValue();
		}
		return parent::getMaxStackSize();
	}
	
	protected function serializeCompoundTag(CompoundTag $tag) : void{
		parent::serializeCompoundTag($tag);
		CustomItemNbtSerializer::serializeToNbt($this, $tag);
	}
	
	protected function deserializeCompoundTag(CompoundTag $tag) : void{
		parent::deserializeCompoundTag($tag);
		// NBT'den yükleme CustomItemFactory tarafından yapılır
	}
}
