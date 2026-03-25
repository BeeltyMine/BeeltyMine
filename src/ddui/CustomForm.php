<?php

declare(strict_types=1);

namespace pocketmine\ddui;

use pocketmine\ddui\element\ButtonElement;
use pocketmine\ddui\element\CloseButtonElement;
use pocketmine\ddui\element\DropdownElement;
use pocketmine\ddui\element\DropdownItem;
use pocketmine\ddui\element\HeaderElement;
use pocketmine\ddui\element\LabelElement;
use pocketmine\ddui\element\SliderElement;
use pocketmine\ddui\element\SpacerElement;
use pocketmine\ddui\element\TextFieldElement;
use pocketmine\ddui\element\ToggleElement;
use pocketmine\ddui\element\options\ButtonOptions;
use pocketmine\ddui\element\options\CloseButtonOptions;
use pocketmine\ddui\element\options\DropdownOptions;
use pocketmine\ddui\element\options\HeaderOptions;
use pocketmine\ddui\element\options\LabelOptions;
use pocketmine\ddui\element\options\SliderElementOptions;
use pocketmine\ddui\element\options\SpacerOptions;
use pocketmine\ddui\element\options\TextFieldOptions;
use pocketmine\ddui\element\options\ToggleOptions;
use pocketmine\ddui\properties\StringProperty;
use pocketmine\player\Player;

final class CustomForm extends DataDrivenScreen{
	public function __construct(string|Observable|null $title = null){
		parent::__construct();
		if($title !== null){
			$this->title($title);
		}
	}

	public function getIdentifier() : string{
		return "minecraft:custom_form";
	}

	public function getDataProperty() : string{
		return "custom_form_data";
	}

	public function title(string|Observable $title) : self{
		if($title instanceof Observable){
			$property = $this->resolveTitleProperty();
			$property->setValue((string) $title->getValue());
			$this->setProperty($property);
			$title->subscribe(function(mixed $value) use ($property){
				$property->setValue((string) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveTitleProperty();
		$property->setValue($title);
		$this->setProperty($property);
		return $this;
	}

	public function closeButton(?CloseButtonOptions $options = null) : self{
		$button = new CloseButtonElement($options, $this);
		$button->addListener(fn(Player $player) => $this->close($player));
		$this->setProperty($button);
		return $this;
	}

	public function button(string|Observable $label, callable $listener, ?ButtonOptions $options = null) : self{
		$button = new ButtonElement($label, $options, $this->layout);
		$button->addListener($listener);
		$this->layout->setProperty($button);
		return $this;
	}

	public function textField(string $label, Observable $text, ?TextFieldOptions $options = null) : self{
		$this->layout->setProperty(new TextFieldElement($label, $text, $options, $this->layout));
		return $this;
	}

	public function slider(string $label, int $minValue, int $maxValue, Observable $currentValue, ?SliderElementOptions $options = null) : self{
		$this->layout->setProperty(new SliderElement($label, $currentValue, $minValue, $maxValue, $options, $this->layout));
		return $this;
	}

	public function label(string|Observable $text, ?LabelOptions $options = null) : self{
		$this->layout->setProperty(new LabelElement($text, $options, $this->layout));
		return $this;
	}

	public function spacer(?SpacerOptions $options = null) : self{
		$this->layout->setProperty(new SpacerElement($options, $this->layout));
		return $this;
	}

	public function toggle(string $label, Observable $toggled, ?ToggleOptions $options = null) : self{
		$this->layout->setProperty(new ToggleElement($label, $toggled, $options, $this->layout));
		return $this;
	}

	public function header(string|Observable $text, ?HeaderOptions $options = null) : self{
		$this->layout->setProperty(new HeaderElement($text, $options, $this->layout));
		return $this;
	}

	/**
	 * @param list<DropdownItem> $items
	 */
	public function dropdown(string $label, array $items, Observable $selected, ?DropdownOptions $options = null) : self{
		$this->layout->setProperty(new DropdownElement($label, $items, $selected, $options, $this->layout));
		return $this;
	}

	private function resolveTitleProperty() : StringProperty{
		$existing = $this->getProperty("title");
		if($existing instanceof StringProperty){
			return $existing;
		}

		return new StringProperty("title", "", $this);
	}
}
