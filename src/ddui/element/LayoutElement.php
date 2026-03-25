<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\properties\DataDrivenProperty;
use pocketmine\ddui\properties\LongProperty;
use pocketmine\ddui\properties\ObjectProperty;

final class LayoutElement extends ObjectProperty{
	public function __construct(ObjectProperty $parent){
		parent::__construct("layout", $parent);
	}

	public function setProperty(DataDrivenProperty $property) : static{
		$property->setName((string) $this->getChildCount());
		parent::setProperty($property);
		parent::setProperty(new LongProperty("length", $this->getChildCount(), $this));
		return $this;
	}

	private function getChildCount() : int{
		$properties = $this->getProperties();
		$count = count($properties);
		if(isset($properties["length"])){
			--$count;
		}

		return $count;
	}
}
