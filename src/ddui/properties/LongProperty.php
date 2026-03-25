<?php

declare(strict_types=1);

namespace pocketmine\ddui\properties;

class LongProperty extends DataDrivenProperty{
	public function __construct(string $name, int $value, ?ObjectProperty $parent = null){
		parent::__construct($name, $value, $parent);
	}

	public function toSchemaValue() : int{
		return (int) $this->getValue();
	}
}
