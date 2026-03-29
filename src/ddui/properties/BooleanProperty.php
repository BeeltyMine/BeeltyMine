<?php

declare(strict_types=1);

namespace pocketmine\ddui\properties;

use pocketmine\network\mcpe\protocol\types\BoolDataStoreValue;

final class BooleanProperty extends DataDrivenProperty{
	public function __construct(string $name, bool $value, ?ObjectProperty $parent = null){
		parent::__construct($name, $value, $parent);
	}

	public function toSchemaValue() : bool{
		return (bool) $this->getValue();
	}

	public function toDataStoreValue() : BoolDataStoreValue{
		return new BoolDataStoreValue($this->toSchemaValue());
	}
}
