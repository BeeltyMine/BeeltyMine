<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\element\options\SpacerOptions;
use pocketmine\ddui\properties\ObjectProperty;

final class SpacerElement extends Element{
	public function __construct(?SpacerOptions $options, ObjectProperty $parent){
		parent::__construct("spacer", $parent);
		$options ??= new SpacerOptions();
		$this->setVisibility($options->visible);
	}

	public function setVisibility(bool|Observable $visible) : static{
		parent::setVisibility($visible);
		if($visible instanceof Observable){
			$property = $this->resolveBooleanProperty("spacer_visible", true);
			$property->setValue((bool) $visible->getValue());
			$this->setProperty($property);
			$visible->subscribe(function(mixed $value) use ($property){
				$property->setValue((bool) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveBooleanProperty("spacer_visible", true);
		$property->setValue($visible);
		$this->setProperty($property);
		return $this;
	}
}
