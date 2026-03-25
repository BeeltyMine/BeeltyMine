<?php

declare(strict_types=1);

namespace pocketmine\ddui\element\options;

use pocketmine\ddui\Observable;

final class CloseButtonOptions implements ElementOptions{
	public function __construct(
		public readonly string|Observable $label = "Close",
		public readonly bool|Observable $visible = true
	){}
}
