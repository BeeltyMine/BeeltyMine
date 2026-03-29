<?php

declare(strict_types=1);

namespace pocketmine\ddui\properties;

use pocketmine\network\mcpe\protocol\types\TypeDataStoreValue;

class ObjectProperty extends DataDrivenProperty{
	/** @var array<string, DataDrivenProperty> */
	private array $properties = [];

	public function __construct(string $name, ?self $parent = null){
		parent::__construct($name, null, $parent);
	}

	public function getProperty(string $name) : ?DataDrivenProperty{
		return $this->properties[$name] ?? null;
	}

	public function setProperty(DataDrivenProperty $property) : static{
		$this->properties[$property->getName()] = $property;
		return $this;
	}

	/**
	 * @return array<string, DataDrivenProperty>
	 */
	public function getProperties() : array{
		return $this->properties;
	}

	public function toSchemaValue() : array{
		$result = [];
		foreach($this->properties as $name => $property){
			$result[$name] = $property->toSchemaValue();
		}

		return $result;
	}

	public function toDataStoreValue() : TypeDataStoreValue{
		$result = [];
		foreach($this->properties as $name => $property){
			$result[$name] = $property->toDataStoreValue();
		}

		return new TypeDataStoreValue($result);
	}
}
