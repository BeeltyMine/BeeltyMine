<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

/**
 * Base interface for all custom item components.
 */
interface ItemComponent{
	
	/**
	 * Returns the component name identifier.
	 */
	public function getName() : string;
	
	/**
	 * Returns the component value for encoding.
	 */
	public function getValue() : mixed;
    
	/**
	 * Whether this component should be treated as an item property (sent under item_properties)
	 */
	public function isProperty() : bool;
}
