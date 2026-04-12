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

namespace pocketmine;

use pocketmine\utils\Config;

final class BeeltySettings{

	private static bool $chemistryItemsEnabled = false;
	private static bool $chemistryBlocksEnabled = false;

	private function __construct(){
		//NOOP
	}

	public static function loadFromConfig(Config $config) : void{
		self::$chemistryItemsEnabled = (bool) $config->getNested("chemistry.items", false);
		self::$chemistryBlocksEnabled = (bool) $config->getNested("chemistry.blocks", false);
	}

	public static function chemistryItemsEnabled() : bool{
		return self::$chemistryItemsEnabled;
	}

	public static function chemistryBlocksEnabled() : bool{
		return self::$chemistryBlocksEnabled;
	}
}