<?php

declare(strict_types=1);

namespace pocketmine\block\utils;

enum PointedDripstoneThickness : string{
	case TIP = "tip";
	case FRUSTUM = "frustum";
	case MIDDLE = "middle";
	case BASE = "base";
	case MERGE = "merge";
}
