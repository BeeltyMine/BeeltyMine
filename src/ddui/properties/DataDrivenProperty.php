<?php

declare(strict_types=1);

namespace pocketmine\ddui\properties;

use pocketmine\ddui\DataDrivenScreen;
use pocketmine\network\mcpe\protocol\types\DataStoreValue;
use pocketmine\player\Player;

abstract class DataDrivenProperty{
	/** @var array<int, callable(Player, mixed) : void> */
	private array $listeners = [];

	private int $triggerCount = 0;

	public function __construct(
		private string $name,
		private mixed $value,
		private ?ObjectProperty $parent = null
	){}

	public function getName() : string{
		return $this->name;
	}

	public function setName(string $name) : static{
		$this->name = $name;
		return $this;
	}

	public function getValue() : mixed{
		return $this->value;
	}

	public function setValue(mixed $value) : static{
		$this->value = $value;
		return $this;
	}

	public function getParent() : ?ObjectProperty{
		return $this->parent;
	}

	public function getTriggerCount() : int{
		return $this->triggerCount;
	}

	public function addListener(callable $listener) : void{
		$this->listeners[] = $listener;
	}

	public function triggerListeners(Player $player, mixed $data) : void{
		++$this->triggerCount;
		foreach($this->listeners as $listener){
			$listener($player, $data);
		}
	}

	public function getPath() : string{
		if($this->parent === null){
			return $this->name;
		}

		$parentPath = $this->parent->getPath();
		if($this->parent->getName() === ""){
			return $this->name;
		}

		if(ctype_digit($this->name)){
			return $parentPath . "[" . $this->name . "]";
		}

		return $parentPath . "." . $this->name;
	}

	public function getRootScreen() : ?DataDrivenScreen{
		return $this->parent?->getRootScreen();
	}

	abstract public function toSchemaValue() : mixed;

	abstract public function toDataStoreValue() : DataStoreValue;
}
