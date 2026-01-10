<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class UseAnimationComponent implements ItemComponent{
	
	public const NONE = "none";
	public const EAT = "eat";
	public const DRINK = "drink";
	public const BLOCK = "block";
	public const BOW = "bow";
	public const CAMERA = "camera";
	public const SPEAR = "spear";
	public const CROSSBOW = "crossbow";
	public const SPYGLASS = "spyglass";
	
	public function __construct(
		private string $value
	){}
	
	public function getName() : string{
		return "minecraft:use_animation";
	}
	
	public function getValue() : string{
		return $this->value;
	}
	
	public function getAnimation() : string{
		return $this->value;
	}
	
	public function isProperty() : bool{
		return true;
	}
}
