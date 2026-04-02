<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use pocketmine\item\Durable;
use pocketmine\item\Item;

class SmithingTransformRecipe implements SmithingRecipe{
	public function __construct(
		private readonly RecipeIngredient $input,
		private readonly RecipeIngredient $addition,
		private readonly RecipeIngredient $template,
		private Item $result
	){
		$this->result = clone $this->result;
	}

	public function getInput() : RecipeIngredient{
		return $this->input;
	}

	public function getAddition() : RecipeIngredient{
		return $this->addition;
	}

	public function getTemplate() : RecipeIngredient{
		return $this->template;
	}

	public function getResult() : Item{
		return clone $this->result;
	}

	/**
	 * @param Item[] $inputs
	 */
	public function getResultFor(array $inputs) : ?Item{
		foreach($inputs as $item){
			if($this->input->accepts($item)){
				$result = $this->getResult()->setNamedTag($item->getNamedTag());
				if($result instanceof Durable && $item instanceof Durable){
					$result->setDamage($item->getDamage());
				}
				return $result;
			}
		}

		return null;
	}
}