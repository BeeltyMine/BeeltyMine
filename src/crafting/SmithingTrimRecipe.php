<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use pocketmine\data\bedrock\ArmorTrimMaterialTypeIdMap;
use pocketmine\data\bedrock\ArmorTrimPatternTypeIdMap;
use pocketmine\item\Armor;
use pocketmine\item\ArmorTrim;
use pocketmine\item\Item;

class SmithingTrimRecipe implements SmithingRecipe{
	public function __construct(
		private readonly RecipeIngredient $input,
		private readonly RecipeIngredient $addition,
		private readonly RecipeIngredient $template
	){}

	public function getInput() : RecipeIngredient{
		return $this->input;
	}

	public function getAddition() : RecipeIngredient{
		return $this->addition;
	}

	public function getTemplate() : RecipeIngredient{
		return $this->template;
	}

	/**
	 * @param Item[] $inputs
	 */
	public function getResultFor(array $inputs) : ?Item{
		$input = null;
		$template = null;
		$addition = null;

		foreach($inputs as $item){
			if($this->input->accepts($item) && $item instanceof Armor){
				$input = $item;
			}elseif($this->addition->accepts($item)){
				$addition = $item;
			}elseif($this->template->accepts($item)){
				$template = $item;
			}
		}

		if($input !== null && $addition !== null && $template !== null){
			$material = ArmorTrimMaterialTypeIdMap::getInstance()->fromItem($addition);
			$pattern = ArmorTrimPatternTypeIdMap::getInstance()->fromItem($template);
			if($material !== null && $pattern !== null){
				return (clone $input)->setTrim(new ArmorTrim($material, $pattern));
			}
		}

		return null;
	}
}