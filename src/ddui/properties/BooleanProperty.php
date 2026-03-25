<?php

declare(strict_types=1);

namespace pocketmine\ddui\properties;

final class BooleanProperty extends DataDrivenProperty{
	public function __construct(string $name, bool $value, ?ObjectProperty $parent = null){
		parent::__construct($name, $value, $parent);
	}

	public function toSchemaValue() : bool{
		return (bool) $this->getValue();
	}
}
