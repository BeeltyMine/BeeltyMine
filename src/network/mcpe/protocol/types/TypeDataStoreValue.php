<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function count;

final class TypeDataStoreValue extends DataStoreValue{
	public const ID = 6;

	/**
	 * @param array<string, DataStoreValue> $value
	 */
	public function __construct(
		private readonly array $value
	){}

	/**
	 * @return array<string, DataStoreValue>
	 */
	public function getValue() : array{
		return $this->value;
	}

	public function getTypeId() : int{
		return self::ID;
	}

	public function write(ByteBufferWriter $out) : void{
		VarInt::writeUnsignedInt($out, count($this->value));
		foreach($this->value as $name => $value){
			CommonTypes::putString($out, (string) $name);
			LE::writeSignedInt($out, $value->getTypeId());
			$value->write($out);
		}
	}

	public static function read(ByteBufferReader $in) : self{
		$values = [];
		for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
			$name = CommonTypes::getString($in);
			$typeId = LE::readSignedInt($in);
			$values[$name] = self::readNestedValue($in, $typeId);
		}

		return new self($values);
	}

	private static function readNestedValue(ByteBufferReader $in, int $typeId) : DataStoreValue{
		return match($typeId){
			BoolDataStoreValue::ID => BoolDataStoreValue::read($in),
			LongDataStoreValue::ID => LongDataStoreValue::read($in),
			StringDataStoreValue::ID => StringDataStoreValue::read($in),
			self::ID => self::read($in),
			default => throw new PacketDecodeException("Unknown DataStoreValueType"),
		};
	}
}
