<?php

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;

class BreezeWindChargeBurstSound implements Sound {

    public function encode(Vector3 $pos) : array {
        $pitch = 1.2;
        return [LevelEventPacket::create(LevelEvent::SOUND_SHOOT, (int)($pitch * 1000), $pos)];
    }
}
