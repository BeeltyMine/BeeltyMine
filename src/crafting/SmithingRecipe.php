<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use pocketmine\item\Item;

interface SmithingRecipe{
	public function getInput() : RecipeIngredient;

	public function getAddition() : RecipeIngredient;

	public function getTemplate() : RecipeIngredient;

	/**
	 * @param Item[] $inputs
	 */
	public function getResultFor(array $inputs) : ?Item;
}