<?php

declare(strict_types=1);

namespace pocketmine\item\customitem;

use pocketmine\item\customitem\component\ItemComponent;
use pocketmine\item\customitem\data\CreativeCategory;
use pocketmine\item\customitem\data\CreativeGroup;

/**
 * Defines the properties and components of a custom item.
 */
final class CustomItemDefinition{
	
	/**
	 * @param ItemComponent[] $components
	 */
	public function __construct(
		private string $identifier,
		private int $numericId,
		private string $displayName,
		private array $components = [],
		private ?CreativeCategory $creativeCategory = null,
		private ?CreativeGroup $creativeGroup = null
	){}
	
	public function getIdentifier() : string{
		return $this->identifier;
	}
	
	public function getNumericId() : int{
		return $this->numericId;
	}
	
	public function getDisplayName() : string{
		return $this->displayName;
	}
	
	/**
	 * @return ItemComponent[]
	 */
	public function getComponents() : array{
		return $this->components;
	}
	
	public function getComponent(string $name) : ?ItemComponent{
		foreach($this->components as $component){
			if($component->getName() === $name){
				return $component;
			}
		}
		return null;
	}
	
	public function hasComponent(string $name) : bool{
		return $this->getComponent($name) !== null;
	}
	
	public function getCreativeCategory() : ?CreativeCategory{
		return $this->creativeCategory;
	}
	
	public function getCreativeGroup() : ?CreativeGroup{
		return $this->creativeGroup;
	}
	
	/**
	 * Returns the item definition data for client encoding.
	 */
	public function toData() : array{
		$data = [
			"id" => $this->numericId,
			"name" => $this->identifier,
			"display_name" => $this->displayName
		];
		
		$components = [];
		foreach($this->components as $component){
			$components[$component->getName()] = $component->getValue();
		}
		
		if(count($components) > 0){
			$data["components"] = $components;
		}
		
		if($this->creativeCategory !== null){
			$data["menu_category"] = [
				"category" => $this->creativeCategory->getTranslationKey()
			];
			
			if($this->creativeGroup !== null){
				$data["menu_category"]["group"] = $this->creativeGroup->getId();
			}
		}
		
		return $data;
	}
}
