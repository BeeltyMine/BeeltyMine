<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\inventory\ArmorInventory;
use pocketmine\item\enchantment\ItemEnchantmentTags as EnchantmentTags;

final class Elytra extends Armor{

	public function __construct(ItemIdentifier $identifier, string $name = "Elytra"){
		parent::__construct(
			$identifier,
			$name,
			new ArmorTypeInfo(0, 432, ArmorInventory::SLOT_CHEST, material: VanillaArmorMaterials::LEATHER()),
			[EnchantmentTags::ELYTRA]
		);
	}

	public function isBroken() : bool{
		return $this->damage >= $this->getMaxDurability() - 1 || $this->isNull();
	}

	protected function onBroken() : void{
		$this->damage = $this->getMaxDurability() - 1;
	}
}
