<?php

declare(strict_types=1);

namespace pocketmine\ddui;

use pocketmine\ddui\properties\DataDrivenProperty;
use pocketmine\network\mcpe\protocol\ClientboundDataStorePacket;
use pocketmine\network\mcpe\protocol\types\DataStoreUpdate;

final class Observable{
	/** @var array<int, callable(mixed) : ?DataDrivenProperty> */
	private array $listeners = [];

	private mixed $value;

	private static int $outboundSuppressionDepth = 0;

	public function __construct(mixed $value){
		$this->value = $value;
	}

	public function getValue() : mixed{
		return $this->value;
	}

	public function setValue(mixed $value) : void{
		$this->value = $value;

		foreach($this->listeners as $listener){
			$property = $listener($value);
			if(!$property instanceof DataDrivenProperty){
				continue;
			}

			if(self::$outboundSuppressionDepth > 0){
				continue;
			}

			$screen = $property->getRootScreen();
			if(!$screen instanceof DataDrivenScreen){
				continue;
			}

			[$dataStore] = explode(":", $screen->getIdentifier(), 2);
			$packet = ClientboundDataStorePacket::create([
				new DataStoreUpdate(
					$dataStore,
					$screen->getDataProperty(),
					$property->getPath(),
					DataStoreValueFactory::fromMixed($value),
					1,
					1
				)
			]);

			foreach($screen->getAllViewers() as $viewer){
				$viewer->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	/**
	 * @param callable(mixed) : ?DataDrivenProperty $listener
	 */
	public function subscribe(callable $listener) : void{
		$this->listeners[] = $listener;
	}

	/**
	 * @param callable(mixed) : ?DataDrivenProperty $listener
	 */
	public function unsubscribe(callable $listener) : void{
		foreach($this->listeners as $index => $registeredListener){
			if($registeredListener === $listener){
				unset($this->listeners[$index]);
			}
		}
	}

	public static function withOutboundSuppressed(callable $callback) : void{
		++self::$outboundSuppressionDepth;
		try{
			$callback();
		}finally{
			--self::$outboundSuppressionDepth;
		}
	}
}
