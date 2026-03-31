<?php

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;

final class FireflyBushAmbientSound implements Sound{
	public function encode(Vector3 $pos) : array{
		return [PlaySoundPacket::create("block.firefly_bush.bush", $pos->x, $pos->y, $pos->z, 1.0, 1.0)];
	}
}
