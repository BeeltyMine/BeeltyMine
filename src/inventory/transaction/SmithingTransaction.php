<?php

declare(strict_types=1);

namespace pocketmine\inventory\transaction;

use pocketmine\crafting\SmithingRecipe;
use pocketmine\player\Player;
use function count;

class SmithingTransaction extends InventoryTransaction{
	public function __construct(
		Player $source,
		private readonly SmithingRecipe $recipe,
		array $actions = []
	){
		parent::__construct($source, $actions);
	}

	public function validate() : void{
		if(count($this->actions) < 1){
			throw new TransactionValidationException('Transaction must have at least one action to be executable');
		}

		$inputs = [];
		$outputs = [];
		$this->matchItems($outputs, $inputs);

		if(($inputCount = count($inputs)) !== 3){
			throw new TransactionValidationException("Expected 3 input items, got $inputCount");
		}
		if(($outputCount = count($outputs)) !== 1){
			throw new TransactionValidationException("Expected 1 output item, but received $outputCount");
		}

		$output = $this->recipe->getResultFor($inputs);
		if($output === null){
			throw new TransactionValidationException("Couldn't find a matching output item for the given inputs");
		}
		if(!$output->equalsExact($outputs[0])){
			throw new TransactionValidationException('Invalid output item');
		}
	}
}