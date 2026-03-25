<?php

declare(strict_types=1);

namespace pocketmine\ddui;

use pocketmine\ddui\properties\BooleanProperty;
use pocketmine\ddui\properties\DataDrivenProperty;
use pocketmine\ddui\properties\LongProperty;
use pocketmine\ddui\properties\StringProperty;
use pocketmine\network\mcpe\protocol\types\BoolDataStoreValue;
use pocketmine\network\mcpe\protocol\types\DataStoreValue;
use pocketmine\network\mcpe\protocol\types\DoubleDataStoreValue;
use pocketmine\network\mcpe\protocol\types\StringDataStoreValue;
use Stringable;

final class DataStoreValueFactory{
	private function __construct(){
		//NOOP
	}

	public static function fromMixed(mixed $value) : DataStoreValue{
		return match(true){
			is_bool($value) => new BoolDataStoreValue($value),
			is_int($value), is_float($value) => new DoubleDataStoreValue((float) $value),
			$value instanceof Stringable => new StringDataStoreValue((string) $value),
			default => new StringDataStoreValue((string) $value),
		};
	}

	public static function castForProperty(DataDrivenProperty $property, DataStoreValue $value) : bool|int|string{
		if($property instanceof BooleanProperty){
			return match(true){
				$value instanceof BoolDataStoreValue => $value->getValue(),
				$value instanceof DoubleDataStoreValue => $value->getValue() !== 0.0,
				$value instanceof StringDataStoreValue => filter_var($value->getValue(), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
				default => false,
			};
		}

		if($property instanceof LongProperty){
			return match(true){
				$value instanceof DoubleDataStoreValue => (int) round($value->getValue()),
				$value instanceof BoolDataStoreValue => $value->getValue() ? 1 : 0,
				$value instanceof StringDataStoreValue && is_numeric($value->getValue()) => (int) round((float) $value->getValue()),
				default => 0,
			};
		}

		if($property instanceof StringProperty){
			return match(true){
				$value instanceof StringDataStoreValue => $value->getValue(),
				$value instanceof BoolDataStoreValue => $value->getValue() ? "true" : "false",
				$value instanceof DoubleDataStoreValue => (string) $value->getValue(),
				default => "",
			};
		}

		return "";
	}
}
