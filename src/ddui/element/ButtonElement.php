<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\element\options\ButtonOptions;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\ddui\properties\StringProperty;
use pocketmine\player\Player;

final class ButtonElement extends Element{
	public function __construct(string|Observable $label, ?ButtonOptions $options, ObjectProperty $parent){
		parent::__construct("button", $parent);
		$options ??= new ButtonOptions();

		$this->setLabel($label);
		$this->setToolTip($options->tooltip);
		$this->setVisibility($options->visible);
		$this->setDisabled($options->disabled);

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

	public function setVisibility(bool|Observable $visible) : static{
		parent::setVisibility($visible);
		if($visible instanceof Observable){
			$property = $this->resolveBooleanProperty("button_visible", true);
			$property->setValue((bool) $visible->getValue());
			$this->setProperty($property);
			$visible->subscribe(function(mixed $value) use ($property){
				$property->setValue((bool) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveBooleanProperty("button_visible", true);
		$property->setValue($visible);
		$this->setProperty($property);
		return $this;
	}

	public function addListener(callable $listener) : void{
		parent::addListener(fn(Player $player, mixed $data) => $listener($player));
	}
}
