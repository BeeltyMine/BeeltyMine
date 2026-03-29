<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;

final class LongDataStoreValue extends DataStoreValue{
	public const ID = 2;

	public function __construct(
		private readonly int $value
	){}

	public function getValue() : int{
		return $this->value;
	}

	public function getTypeId() : int{
		return self::ID;
	}

	public function write(ByteBufferWriter $out) : void{
		LE::writeSignedLong($out, $this->value);
	}

	public static function read(ByteBufferReader $in) : self{
		return new self(LE::readSignedLong($in));
	}
}
