<?php

declare(strict_types=1);

namespace pocketmine\ddui\element\options;

use pocketmine\ddui\Observable;

final class TextFieldOptions implements ElementOptions{
	public function __construct(
		public readonly string|Observable $description = "",
		public readonly bool|Observable $disabled = false,
		public readonly bool|Observable $visible = true
	){}
}
