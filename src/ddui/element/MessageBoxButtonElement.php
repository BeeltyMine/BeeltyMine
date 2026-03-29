<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\ddui\properties\StringProperty;
use pocketmine\player\Player;

final class MessageBoxButtonElement extends Element{
	public function __construct(string $name, string|Observable $label, string|Observable $tooltip, ObjectProperty $parent){
		parent::__construct($name, $parent);

		$this->setLabel($label);
		$this->setToolTip($tooltip);

		$clickElement = new ButtonClickElement($this);
		$clickElement->addListener(fn(Player $player, mixed $data) => $this->triggerListeners($player, $data));
		$this->setProperty($clickElement);
	}

	public function getToolTip() : string{
		$property = $this->getProperty("tooltip");
		return $property instanceof StringProperty ? $property->getValue() : "";
	}

	public function setToolTip(string|Observable $tooltip) : static{
		if($tooltip instanceof Observable){
			$property = $this->resolveStringProperty("tooltip");
			$property->setValue((string) $tooltip->getValue());
			$this->setProperty($property);
			$tooltip->subscribe(function(mixed $value) use ($property){
				$property->setValue((string) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveStringProperty("tooltip");
		$property->setValue($tooltip);
		$this->setProperty($property);
		return $this;
	}

	public function addListener(callable $listener) : void{
		parent::addListener(fn(Player $player, mixed $data) => $listener($player));
	}
}
