<?php

/*
 *     ____            ____        __  ____
 *    / __ )___  ___  / / /___  __/  |/  (_)___  ___
 *   / __  / _ \/ _ \/ / __/ / / / /|_/ / / __ \/ _ \
 *  / /_/ /  __/  __/ / /_/ /_/ / /  / / / / / /  __/
 * /_____/\___/\___/_/\__/\__, /_/  /_/_/_/ /_/\___/
 *                       /____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Ayrz
 * @team BeeltyMine
 * 
 * 
 */

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\utils\AssumptionFailedError;

final class CrossbowLoadSound implements Sound{

	public const LOADING_START = 0;
	public const LOADING_MIDDLE = 1;
	public const LOADING_END = 2;

	public function __construct(
		private readonly int $type,
		private readonly bool $quickCharge
	){}

	public function encode(Vector3 $pos) : array{
		$sound = match($this->type){
			self::LOADING_START => $this->quickCharge ? LevelSoundEvent::CROSSBOW_QUICK_CHARGE_START : LevelSoundEvent::CROSSBOW_LOADING_START,
			self::LOADING_MIDDLE => $this->quickCharge ? LevelSoundEvent::CROSSBOW_QUICK_CHARGE_MIDDLE : LevelSoundEvent::CROSSBOW_LOADING_MIDDLE,
			self::LOADING_END => $this->quickCharge ? LevelSoundEvent::CROSSBOW_QUICK_CHARGE_END : LevelSoundEvent::CROSSBOW_LOADING_END,
			default => throw new AssumptionFailedError("Unknown crossbow loading sound type $this->type")
		};

		return [LevelSoundEventPacket::nonActorSound($sound, $pos, false)];
	}
}
