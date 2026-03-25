<?php

declare(strict_types=1);

namespace pocketmine\ddui\element;

final class DropdownItem{
	public function __construct(
		public readonly string $label,
		public readonly string $description = ""
	){}
}
