<?php

declare(strict_types=1);

namespace pocketmine\ddui\element\options;

use pocketmine\ddui\Observable;

final class ButtonOptions implements ElementOptions{
	public function __construct(
		public readonly bool|Observable $disabled = false,
		public readonly string|Observable $tooltip = "",
		public readonly bool|Observable $visible = true
	){}
}
