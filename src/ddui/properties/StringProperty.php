<?php

declare(strict_types=1);

namespace pocketmine\ddui\properties;

use pocketmine\network\mcpe\protocol\types\StringDataStoreValue;

final class StringProperty extends DataDrivenProperty{
	public function __construct(string $name, string $value, ?ObjectProperty $parent = null){
		parent::__construct($name, $value, $parent);
	}

	public function toSchemaValue() : string{
		return (string) $this->getValue();
	}

	public function toDataStoreValue() : StringDataStoreValue{
		return new StringDataStoreValue($this->toSchemaValue());
	}
}
