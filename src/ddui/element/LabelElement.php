<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\element\options\LabelOptions;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\ddui\properties\StringProperty;

final class LabelElement extends Element{
	public function __construct(string|Observable $text, ?LabelOptions $options, ObjectProperty $parent){
		parent::__construct("label", $parent);
		$options ??= new LabelOptions();

		$this->setText($text);
		$this->setVisibility($options->visible);
	}

	public function getText() : string{
		$property = $this->getProperty("text");
		return $property instanceof StringProperty ? $property->getValue() : "";
	}

	public function setText(string|Observable $text) : static{
		if($text instanceof Observable){
			$property = $this->resolveStringProperty("text");
			$property->setValue((string) $text->getValue());
			$this->setProperty($property);
			$text->subscribe(function(mixed $value) use ($property){
				$property->setValue((string) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveStringProperty("text");
		$property->setValue($text);
		$this->setProperty($property);
		return $this;
	}

	public function setVisibility(bool|Observable $visible) : static{
		parent::setVisibility($visible);
		if($visible instanceof Observable){
			$property = $this->resolveBooleanProperty("label_visible", true);
			$property->setValue((bool) $visible->getValue());
			$this->setProperty($property);
			$visible->subscribe(function(mixed $value) use ($property){
				$property->setValue((bool) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveBooleanProperty("label_visible", true);
		$property->setValue($visible);
		$this->setProperty($property);
		return $this;
	}
}
