<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\element\options\TextFieldOptions;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\ddui\properties\StringProperty;
use pocketmine\player\Player;

final class TextFieldElement extends Element{
	public function __construct(
		string $label,
		private Observable $text,
		?TextFieldOptions $options,
		ObjectProperty $parent
	){
		parent::__construct("textField", $parent);
		$options ??= new TextFieldOptions();

		$this->setLabel($label);
		$this->setText($text);
		$this->setVisibility($options->visible);
		$this->setTextFieldVisible($options->visible);
		$this->setDisabled($options->disabled);
		$this->setDescription($options->description);
	}

	public function getText() : string{
		$property = $this->getProperty("text");
		return $property instanceof StringProperty ? $property->getValue() : "";
	}

	public function setText(string|Observable $text) : static{
		if($text instanceof Observable){
			$property = $this->resolveTextProperty();
			$property->setValue((string) $text->getValue());
			$this->setProperty($property);
			$text->subscribe(function(mixed $value) use ($property){
				$property->setValue((string) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveTextProperty();
		$property->setValue($text);
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

	public function setTextFieldVisible(bool|Observable $visible) : static{
		if($visible instanceof Observable){
			$property = $this->resolveBooleanProperty("textfield_visible", true);
			$property->setValue((bool) $visible->getValue());
			$this->setProperty($property);
			$visible->subscribe(function(mixed $value) use ($property){
				$property->setValue((bool) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveBooleanProperty("textfield_visible", true);
		$property->setValue($visible);
		$this->setProperty($property);
		return $this;
	}

	public function triggerListeners(Player $player, mixed $data) : void{
		parent::triggerListeners($player, $data);

		if(is_string($data)){
			$this->setText($data);
			$this->text->setValue($data);
		}
	}

	private function resolveTextProperty() : StringProperty{
		$existing = $this->getProperty("text");
		if($existing instanceof StringProperty){
			return $existing;
		}

		$property = new StringProperty("text", "", $this);
		$property->addListener(fn(Player $player, mixed $data) => $this->triggerListeners($player, (string) $data));
		return $property;
	}
}
