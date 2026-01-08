<?php

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

class WindChargeShootSound implements Sound {

    public function __construct(private float $pitch = 1.0, private float $volume = 1.0){
        if($this->volume < 0 || $this->volume > 1){
            throw new \InvalidArgumentException("Volume must be between 0 and 1");
        }
    }

    public function encode(Vector3 $pos) : array{
        return [LevelSoundEventPacket::create(
            LevelSoundEvent::WIND_CHARGE_BURST,
            $pos,
            (int) ($this->volume * 16777215),
            ":",
            false,
            false,
            -1
        )];
    }
}
