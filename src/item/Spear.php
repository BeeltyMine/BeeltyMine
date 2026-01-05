<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\block\BlockToolType;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\item\enchantment\VanillaEnchantments as Enchantments;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\Server;

class Spear extends TieredTool implements Releasable{

	public function getBlockToolType(): int{
		return BlockToolType::SPEAR;
	}

	public function canStartUsingItem(Player $player): bool
	{
		return true;
	}

	// notice: Temporarily suspended due to inconsistent behavior
	// Organizer: Ayrz

}
