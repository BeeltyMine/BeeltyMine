<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\element\options\ToggleOptions;
use pocketmine\ddui\properties\BooleanProperty;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\ddui\properties\StringProperty;
use pocketmine\player\Player;

final class ToggleElement extends Element{
	public function __construct(
		string $label,
		private Observable $toggled,
		?ToggleOptions $options,
		ObjectProperty $parent
	){
		parent::__construct("toggle", $parent);
		$options ??= new ToggleOptions();

		$this->setLabel($label);
		$this->setToggled($toggled);
		$this->setVisibility($options->visible);
		$this->setDisabled($options->disabled);
		$this->setDescription($options->description);
	}

	public function setToggled(bool|Observable $value) : static{
		if($value instanceof Observable){
			$property = $this->resolveToggledProperty();
			$property->setValue((bool) $value->getValue());
			$this->setProperty($property);
			$value->subscribe(function(mixed $next) use ($property){
				$property->setValue((bool) $next);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveToggledProperty();
		$property->setValue($value);
		$this->setProperty($property);
		return $this;
	}

	public function getDescription() : string{
		$property = $this->getProperty("description");
		return $property instanceof StringProperty ? $property->getValue() : "";
	}

	public function setDescription(string|Observable $description) : static{
		if($description instanceof Observable){
			$property = $this->resolveStringProperty("description");
			$property->setValue((string) $description->getValue());
			$this->setProperty($property);
			$description->subscribe(function(mixed $value) use ($property){
				$property->setValue((string) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveStringProperty("description");
		$property->setValue($description);
		$this->setProperty($property);
		return $this;
	}

	public function setVisibility(bool|Observable $visible) : static{
		parent::setVisibility($visible);
		if($visible instanceof Observable){
			$property = $this->resolveBooleanProperty("toggle_visible", true);
			$property->setValue((bool) $visible->getValue());
			$this->setProperty($property);
			$visible->subscribe(function(mixed $value) use ($property){
				$property->setValue((bool) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveBooleanProperty("toggle_visible", true);
		$property->setValue($visible);
		$this->setProperty($property);
		return $this;
	}

	public function triggerListeners(Player $player, mixed $data) : void{
		parent::triggerListeners($player, $data);

		if(is_bool($data)){
			$this->setToggled($data);
			$this->toggled->setValue($data);
		}
	}

	private function resolveToggledProperty() : BooleanProperty{
		$existing = $this->getProperty("toggled");
		if($existing instanceof BooleanProperty){
			return $existing;
		}

		$property = new BooleanProperty("toggled", false, $this);
		$property->addListener(fn(Player $player, mixed $data) => $this->triggerListeners($player, (bool) $data));
		return $property;
	}
}
