<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class RecordComponent implements ItemComponent{
	
	public function __construct(
		private string $soundEvent,
		private float $duration,
		private int $comparatorSignal
	){}
	
	public function getName() : string{
		return "minecraft:record";
	}
	
	public function getValue() : array{
		return [
			"sound_event" => $this->soundEvent,
			"duration" => $this->duration,
			"comparator_signal" => $this->comparatorSignal
		];
	}
	
	public function getSoundEvent() : string{
		return $this->soundEvent;
	}
	
	public function getDuration() : float{
		return $this->duration;
	}
	
	public function getComparatorSignal() : int{
		return $this->comparatorSignal;
	}
	
	public function isProperty() : bool{
		return false;
	}
}
