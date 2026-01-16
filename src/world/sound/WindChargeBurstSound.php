<?php

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

class WindChargeBurstSound implements Sound {

    public function encode(Vector3 $pos) : array {
        return [LevelSoundEventPacket::create(
            LevelSoundEvent::WIND_CHARGE_BURST,
            $pos,
            (int) (1.0 * 16777215),
            ":",
            false,
            false,
            -1
        )];
    }
}
