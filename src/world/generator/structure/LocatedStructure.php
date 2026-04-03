<?php

declare(strict_types=1);

namespace pocketmine\world\generator\structure;

final class LocatedStructure{
	public function __construct(
		public readonly StructureType $type,
		public readonly int $chunkX,
		public readonly int $chunkZ,
		public readonly int $x,
		public readonly int $z
	){}
}
