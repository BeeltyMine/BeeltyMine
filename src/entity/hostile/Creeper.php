<?php

namespace pocketmine\entity\hostile;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;

class Creeper extends Living
{
    public static function getNetworkTypeId(): string
    {
        return 'minecraft:creeper';
    }

    public function getName(): string
    {
        return 'Creeper';
    }

    public function getDrops(): array
    {
        return [];
    }

    public function getXpDropAmount(): int
    {
        return 0;
    }

    public function getInitialDragMultiplier(): float
    {
        return 0.02;
    }
    public function getInitialGravity(): float
    {
        return 0.08;
    }

    public function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.6, 1.7);
    }
}