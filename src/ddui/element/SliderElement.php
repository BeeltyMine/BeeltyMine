<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\element\options\SliderElementOptions;
use pocketmine\ddui\properties\LongProperty;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\ddui\properties\StringProperty;
use pocketmine\player\Player;

final class SliderElement extends Element{
	public function __construct(
		string $label,
		private Observable $currentValue,
		int $minValue,
		int $maxValue,
		?SliderElementOptions $options,
		ObjectProperty $parent
	){
		parent::__construct("slider", $parent);
		$options ??= new SliderElementOptions();

		$this->setLabel($label);
		$this->setVisibility($options->visible);
		$this->setDisabled($options->disabled);
		$this->setStep($options->step);
		$this->setMinValue($minValue);
		$this->setMaxValue($maxValue);
		$this->setSliderValue($currentValue);
		$this->setDescription($options->description);
	}

	public function setMinValue(int|Observable $value) : static{
		if($value instanceof Observable){
			$property = $this->resolveLongProperty("minValue");
			$property->setValue((int) $value->getValue());
			$this->setProperty($property);
			$value->subscribe(function(mixed $next) use ($property){
				$property->setValue((int) $next);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveLongProperty("minValue");
		$property->setValue($value);
		$this->setProperty($property);
		return $this;
	}

	public function setMaxValue(int|Observable $value) : static{
		if($value instanceof Observable){
			$property = $this->resolveLongProperty("maxValue");
			$property->setValue((int) $value->getValue());
			$this->setProperty($property);
			$value->subscribe(function(mixed $next) use ($property){
				$property->setValue((int) $next);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveLongProperty("maxValue");
		$property->setValue($value);
		$this->setProperty($property);
		return $this;
	}

	public function setStep(int|Observable $step) : static{
		if($step instanceof Observable){
			$property = $this->resolveLongProperty("step", 1);
			$property->setValue((int) $step->getValue());
			$this->setProperty($property);
			$step->subscribe(function(mixed $next) use ($property){
				$property->setValue((int) $next);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveLongProperty("step", 1);
		$property->setValue($step);
		$this->setProperty($property);
		return $this;
	}

	public function setSliderValue(int|Observable $value) : static{
		if($value instanceof Observable){
			$property = $this->resolveValueProperty();
			$property->setValue((int) $value->getValue());
			$this->setProperty($property);
			$value->subscribe(function(mixed $next) use ($property){
				$property->setValue((int) $next);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveValueProperty();
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
			$property = $this->resolveBooleanProperty("slider_visible", true);
			$property->setValue((bool) $visible->getValue());
			$this->setProperty($property);
			$visible->subscribe(function(mixed $value) use ($property){
				$property->setValue((bool) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveBooleanProperty("slider_visible", true);
		$property->setValue($visible);
		$this->setProperty($property);
		return $this;
	}

	public function triggerListeners(Player $player, mixed $data) : void{
		parent::triggerListeners($player, $data);

		if(is_int($data)){
			$this->setSliderValue($data);
			$this->currentValue->setValue($data);
		}
	}

	private function resolveValueProperty() : LongProperty{
		$existing = $this->getProperty("value");
		if($existing instanceof LongProperty){
			return $existing;
		}

		$property = new LongProperty("value", 0, $this);
		$property->addListener(fn(Player $player, mixed $data) => $this->triggerListeners($player, (int) $data));
		return $property;
	}
}
