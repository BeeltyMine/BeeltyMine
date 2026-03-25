<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

use pocketmine\ddui\Observable;
use pocketmine\ddui\element\options\DropdownOptions;
use pocketmine\ddui\properties\LongProperty;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\ddui\properties\StringProperty;
use pocketmine\player\Player;

final class DropdownElement extends Element{
	/**
	 * @param list<DropdownItem> $items
	 */
	public function __construct(
		string $label,
		private array $items,
		private Observable $selectedIndex,
		?DropdownOptions $options,
		ObjectProperty $parent
	){
		parent::__construct("dropdown", $parent);
		$options ??= new DropdownOptions();

		$this->setLabel($label);
		$this->buildItemsProperty();
		$this->setSelectedIndex($selectedIndex);
		$this->setVisibility($options->visible);
		$this->setDisabled($options->disabled);
		$this->setDescription($options->description);
	}

	public function setSelectedIndex(int|Observable $index) : static{
		if($index instanceof Observable){
			$property = $this->resolveValueProperty();
			$property->setValue((int) $index->getValue());
			$this->setProperty($property);
			$index->subscribe(function(mixed $value) use ($property){
				$property->setValue((int) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveValueProperty();
		$property->setValue($index);
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
			$property = $this->resolveBooleanProperty("dropdown_visible", true);
			$property->setValue((bool) $visible->getValue());
			$this->setProperty($property);
			$visible->subscribe(function(mixed $value) use ($property){
				$property->setValue((bool) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveBooleanProperty("dropdown_visible", true);
		$property->setValue($visible);
		$this->setProperty($property);
		return $this;
	}

	public function triggerListeners(Player $player, mixed $data) : void{
		parent::triggerListeners($player, $data);

		if(is_int($data)){
			$this->setSelectedIndex($data);
			$this->selectedIndex->setValue($data);
		}
	}

	private function buildItemsProperty() : void{
		$itemsProperty = new ObjectProperty("items", $this);
		foreach($this->items as $index => $item){
			$itemProperty = new ObjectProperty((string) $index, $itemsProperty);
			$itemProperty->setProperty(new StringProperty("label", $item->label, $itemProperty));
			$itemProperty->setProperty(new StringProperty("description", $item->description, $itemProperty));
			$itemProperty->setProperty(new LongProperty("value", $index, $itemProperty));
			$itemsProperty->setProperty($itemProperty);
		}
		$itemsProperty->setProperty(new LongProperty("length", count($this->items), $itemsProperty));
		$this->setProperty($itemsProperty);
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
