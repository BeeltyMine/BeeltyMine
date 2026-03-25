<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\element\options\CloseButtonOptions;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\player\Player;

final class CloseButtonElement extends Element{
	public function __construct(?CloseButtonOptions $options, ObjectProperty $parent){
		parent::__construct("closeButton", $parent);
		$options ??= new CloseButtonOptions();

		$this->setLabel($options->label);
		$this->setVisibility($options->visible);

		$clickElement = new ButtonClickElement($this);
		$clickElement->addListener(fn(Player $player, mixed $data) => $this->triggerListeners($player, $data));
		$this->setProperty($clickElement);
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
