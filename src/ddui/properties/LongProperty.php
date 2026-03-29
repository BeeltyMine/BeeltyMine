<?php

declare(strict_types=1);

namespace pocketmine\ddui\properties;

use pocketmine\network\mcpe\protocol\types\LongDataStoreValue;

class LongProperty extends DataDrivenProperty{
	public function __construct(string $name, int $value, ?ObjectProperty $parent = null){
		parent::__construct($name, $value, $parent);
	}

	public function toSchemaValue() : int{
		return (int) $this->getValue();
	}

	public function toDataStoreValue() : LongDataStoreValue{
		return new LongDataStoreValue($this->toSchemaValue());
	}
}
