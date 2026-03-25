<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\properties\BooleanProperty;
use pocketmine\ddui\properties\LongProperty;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\ddui\properties\StringProperty;

abstract class Element extends ObjectProperty{
	protected function __construct(string $name, ObjectProperty $parent){
		parent::__construct($name, $parent);
	}

	public function getLabel() : string{
		$property = $this->getProperty("label");
		return $property instanceof StringProperty ? $property->getValue() : "";
	}

	public function setLabel(string|Observable $label) : static{
		if($label instanceof Observable){
			$property = $this->resolveStringProperty("label");
			$property->setValue((string) $label->getValue());
			$this->setProperty($property);
			$label->subscribe(function(mixed $value) use ($property){
				$property->setValue((string) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveStringProperty("label");
		$property->setValue($label);
		$this->setProperty($property);
		return $this;
	}

	public function getDisabled() : bool{
		$property = $this->getProperty("disabled");
		return $property instanceof BooleanProperty ? $property->getValue() : false;
	}

	public function setDisabled(bool|Observable $disabled) : static{
		if($disabled instanceof Observable){
			$property = $this->resolveBooleanProperty("disabled");
			$property->setValue((bool) $disabled->getValue());
			$this->setProperty($property);
			$disabled->subscribe(function(mixed $value) use ($property){
				$property->setValue((bool) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveBooleanProperty("disabled");
		$property->setValue($disabled);
		$this->setProperty($property);
		return $this;
	}

	public function getVisibility() : bool{
		$property = $this->getProperty("visible");
		return $property instanceof BooleanProperty ? $property->getValue() : true;
	}

	public function setVisibility(bool|Observable $visible) : static{
		if($visible instanceof Observable){
			$property = $this->resolveBooleanProperty("visible", true);
			$property->setValue((bool) $visible->getValue());
			$this->setProperty($property);
			$visible->subscribe(function(mixed $value) use ($property){
				$property->setValue((bool) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveBooleanProperty("visible", true);
		$property->setValue($visible);
		$this->setProperty($property);
		return $this;
	}

	protected function resolveStringProperty(string $name, string $default = "") : StringProperty{
		$existing = $this->getProperty($name);
		if($existing instanceof StringProperty){
			return $existing;
		}

		return new StringProperty($name, $default, $this);
	}

	protected function resolveBooleanProperty(string $name, bool $default = false) : BooleanProperty{
		$existing = $this->getProperty($name);
		if($existing instanceof BooleanProperty){
			return $existing;
		}

		return new BooleanProperty($name, $default, $this);
	}

	protected function resolveLongProperty(string $name, int $default = 0) : LongProperty{
		$existing = $this->getProperty($name);
		if($existing instanceof LongProperty){
			return $existing;
		}

		return new LongProperty($name, $default, $this);
	}
}
