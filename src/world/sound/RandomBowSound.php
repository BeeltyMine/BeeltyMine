<?php

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;

class RandomBowSound implements Sound {

    private float $pitch;

    public function __construct()
    {
        $this->pitch = mt_rand(33, 50) / 100.0;
    }

    public function encode(Vector3 $pos) : array {
        return [LevelEventPacket::create(LevelEvent::SOUND_SHOOT, (int)($this->pitch * 1000), $pos)];
    }
}
