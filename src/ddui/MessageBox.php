<?php

declare(strict_types=1);

namespace pocketmine\ddui;

use pocketmine\ddui\element\MessageBoxButtonElement;
use pocketmine\ddui\properties\StringProperty;
use pocketmine\player\Player;

final class MessageBox extends DataDrivenScreen{
	public function __construct(string|Observable|null $title = null, string|Observable|null $body = null){
		parent::__construct();
		if($title !== null){
			$this->title($title);
		}
		if($body !== null){
			$this->body($body);
		}
	}

	public function getIdentifier() : string{
		return "minecraft:message_box";
	}

	public function getDataProperty() : string{
		return "message_box_data";
	}

	public function title(string|Observable $title) : self{
		if($title instanceof Observable){
			$property = $this->resolveStringProperty("title");
			$property->setValue((string) $title->getValue());
			$this->setProperty($property);
			$title->subscribe(function(mixed $value) use ($property){
				$property->setValue((string) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveStringProperty("title");
		$property->setValue($title);
		$this->setProperty($property);
		return $this;
	}

	public function body(string|Observable $body) : self{
		if($body instanceof Observable){
			$property = $this->resolveStringProperty("body");
			$property->setValue((string) $body->getValue());
			$this->setProperty($property);
			$body->subscribe(function(mixed $value) use ($property){
				$property->setValue((string) $value);
				$this->setProperty($property);
				return $property;
			});
			return $this;
		}

		$property = $this->resolveStringProperty("body");
		$property->setValue($body);
		$this->setProperty($property);
		return $this;
	}

	public function button1(string|Observable $label, callable $listener, string|Observable $tooltip = "") : self{
		$button = new MessageBoxButtonElement("button1", $label, $tooltip, $this);
		$button->addListener($listener);
		$this->setProperty($button);
		return $this;
	}

	public function button2(string|Observable $label, callable $listener, string|Observable $tooltip = "") : self{
		$button = new MessageBoxButtonElement("button2", $label, $tooltip, $this);
		$button->addListener($listener);
		$this->setProperty($button);
		return $this;
	}

	private function resolveStringProperty(string $name) : StringProperty{
		$existing = $this->getProperty($name);
		if($existing instanceof StringProperty){
			return $existing;
		}

		return new StringProperty($name, "", $this);
	}
}
