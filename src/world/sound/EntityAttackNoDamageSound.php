<?php

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

/**
 * Played when a player attacks a mob, but fails to deal damage (e.g. cancelled or attack cooldown).
 */
class EntityAttackNoDamageSound implements Sound{

	public function encode(Vector3 $pos) : array{
		return [LevelSoundEventPacket::create(
			LevelSoundEvent::ATTACK_NODAMAGE,
			$pos,
			-1,
			"minecraft:player",
			false,
			false,
			-1,
			null
		)];
	}
}