<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\data;

/**
 * Represents a creative inventory category for custom items.
 */
enum CreativeCategory : string{
	case NONE = "";
	case CONSTRUCTION = "itemGroup.name.construction";
	case NATURE = "itemGroup.name.nature";
	case EQUIPMENT = "itemGroup.name.equipment";
	case ITEMS = "itemGroup.name.items";
	case COMMANDS = "itemGroup.name.commands";
	
	public function getTranslationKey() : string{
		return $this->value;
	}
}
