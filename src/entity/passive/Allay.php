<?php

declare(strict_types=1);

namespace pocketmine\entity\passive;

use pocketmine\data\SavedDataLoadingException;
use pocketmine\entity\ai\goal\FloatGoal;
use pocketmine\entity\ai\goal\LookAtPlayerGoal;
use pocketmine\entity\ai\goal\RandomFlyGoal;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryHolder;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\sound\PopSound;
use function atan2;
use function max;
use function min;
use function rad2deg;
use function sqrt;
use const PHP_FLOAT_MAX;

class Allay extends Living implements InventoryHolder
{
    private const TAG_OWNER = 'AllayOwner';
    private const TAG_MEMORY_ITEM = 'AllayMemory';
    private const TAG_CARRIED_ITEM = 'AllayCarried';

    private const PICKUP_INTERVAL_TICKS = 10;
    private const PICKUP_RADIUS = 8.0;
    private const DELIVERY_RADIUS_SQ = 9.0;
    private const DELIVERY_COOLDOWN_TICKS = 60;
    private const FOLLOW_SPEED = 0.18;
    private const TARGET_REACH_DISTANCE = 2.5;
    private const MIN_HOVER_HEIGHT = 1.5;

    private SimpleInventory $pouch;
    private ?Item $memoryItem = null;
    private ?string $ownerName = null;
    private int $pickupCooldown = 0;
    private int $lastDeliveryTick = -self::DELIVERY_COOLDOWN_TICKS;

    public function __construct(Location $location, ?CompoundTag $nbt = null)
    {
        $this->pouch = new SimpleInventory(1);
        parent::__construct($location, $nbt);
    }

    public static function getNetworkTypeId(): string
    {
        return 'minecraft:allay';
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.6, 0.6);
    }

    protected function getInitialDragMultiplier(): float
    {
        return 0.08;
    }
    protected function getInitialGravity(): float
    {
        return 0.04;
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        $this->setMaxHealth(20);
        parent::initEntity($nbt);

        $this->setHealth($this->getMaxHealth());
        $this->setHasGravity(false);
        $this->pouch->clearAll();

        $owner = $nbt->getString(self::TAG_OWNER, '');
        $this->ownerName = $owner !== '' ? $owner : null;

        if (($memoryTag = $nbt->getCompoundTag(self::TAG_MEMORY_ITEM)) !== null) {
            try {
                $this->setMemoryItem(Item::nbtDeserialize($memoryTag));
            } catch (SavedDataLoadingException) {
                $this->setMemoryItem(null);
            }
        }

        if (($carriedTag = $nbt->getCompoundTag(self::TAG_CARRIED_ITEM)) !== null) {
            try {
                $this->setCarriedItem(Item::nbtDeserialize($carriedTag));
            } catch (SavedDataLoadingException) {
                $this->pouch->clearAll();
            }
        }

        $this->updateHeldItemVisual();
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        if ($this->ownerName !== null) {
            $nbt->setString(self::TAG_OWNER, $this->ownerName);
        }
        if ($this->memoryItem !== null && !$this->memoryItem->isNull()) {
            $nbt->setTag(self::TAG_MEMORY_ITEM, $this->memoryItem->nbtSerialize());
        }
        $carried = $this->getCarriedItem();
        if (!$carried->isNull()) {
            $nbt->setTag(self::TAG_CARRIED_ITEM, $carried->nbtSerialize());
        }
        return $nbt;
    }

    protected function registerGoals(): void
    {
        parent::registerGoals();
        $goals = $this->getGoalSelector();
        $goals->addGoal(0, new FloatGoal($this));
        $goals->addGoal(1, new RandomFlyGoal($this, 0.15, 40, 120, 6.0, 3.0));
        $goals->addGoal(2, new LookAtPlayerGoal($this, 8.0));
    }


    public function getInventory(): Inventory
    {
        return $this->pouch;
    }

    public function getName(): string
    {
        return 'Allay';
    }

    /**
     * @return Item[]
     */
    public function getDrops(): array
    {
        $drops = [];
        $carried = $this->getCarriedItem();
        if (!$carried->isNull()) {
            $drops[] = $carried;
        }
        if ($this->memoryItem !== null && !$this->memoryItem->isNull()) {
            $drops[] = clone $this->memoryItem;
        }
        return $drops;
    }

    public function getXpDropAmount(): int
    {
        return 0;
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool
    {
        $inventory = $player->getInventory();
        $held = $inventory->getItemInHand();

        if ($held->isNull()) {
            if (!$this->getCarriedItem()->isNull()) {
                $this->dropCarriedItems();
                $player->getWorld()->addSound($this->getLocation(), new PopSound());
                return true;
            }
            if ($this->memoryItem !== null && !$this->memoryItem->isNull()) {
                $player->getWorld()->dropItem(
                    $player->getLocation()->add(0.0, 0.8, 0.0),
                    clone $this->memoryItem,
                    new Vector3(0.0, 0.15, 0.0)
                );
                $this->setMemoryItem(null);
                $this->setOwnerName(null);
                $player->getWorld()->addSound($this->getLocation(), new PopSound());
                return true;
            }
            return false;
        }

        $this->setOwnerName($player->getName());
        $newMemory = clone $held;
        $newMemory->setCount(1);

        if ($this->memoryItem !== null && !$this->memoryItem->isNull()) {
            $player->getWorld()->dropItem(
                $player->getLocation()->add(0.0, 0.8, 0.0),
                clone $this->memoryItem,
                new Vector3(0.0, 0.15, 0.0)
            );
        }

        if ($player->hasFiniteResources()) {
            $held->pop();
            $inventory->setItemInHand($held);
        }

        $this->setMemoryItem($newMemory);
        $player->getWorld()->addSound($this->getLocation(), new PopSound());
        return true;
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);

        if ($this->pickupCooldown > 0) {
            $this->pickupCooldown -= $tickDiff;
        }

        if ($this->pickupCooldown <= 0) {
            if ($this->tryPickupDroppedItem() || ($this->memoryItem !== null && !$this->memoryItem->isNull() && $this->tryCollectNearbyItem())) {
                $hasUpdate = true;
            }
            $this->pickupCooldown = self::PICKUP_INTERVAL_TICKS;
        }

        if (!$this->getCarriedItem()->isNull() && $this->ownerName !== null) {
            $owner = $this->getOwner();
            if ($owner !== null && $owner->isAlive()) {
                if ($owner->getLocation()->distanceSquared($this->getLocation()) <= self::DELIVERY_RADIUS_SQ) {
                    if ($this->deliverItemsToOwner($owner)) {
                        $hasUpdate = true;
                    }
                }
            }
        }

        $this->updateBehaviourTargets();
        $this->maintainHoverHeight();

        return $hasUpdate;
    }

    private function tryPickupDroppedItem(): bool
    {
        if ($this->memoryItem !== null && !$this->memoryItem->isNull()) {
            return false;
        }

        $bb = $this->getBoundingBox()->expandedCopy(3.0, 2.0, 3.0);
        foreach ($this->getWorld()->getNearbyEntities($bb, $this) as $entity) {
            if (!$entity instanceof ItemEntity || $entity->isFlaggedForDespawn()) {
                continue;
            }
            if ($entity->getPickupDelay() > 20) {
                continue;
            }

            $stack = clone $entity->getItem();
            if ($stack->isNull()) {
                continue;
            }

            $this->setMemoryItem($stack);
            $entity->flagForDespawn();

            $thrower = $entity->getThrower();
            if ($thrower !== '') {
                $this->setOwnerName($thrower);
            }

            $this->getWorld()->addSound($this->getLocation(), new PopSound());
            return true;
        }
        return false;
    }

    private function tryCollectNearbyItem(): bool
    {
        if ($this->memoryItem === null || $this->memoryItem->isNull()) {
            return false;
        }

        $bb = $this->getBoundingBox()->expandedCopy(self::PICKUP_RADIUS, 2.0, self::PICKUP_RADIUS);
        foreach ($this->getWorld()->getNearbyEntities($bb, $this) as $entity) {
            if (!$entity instanceof ItemEntity || $entity->isFlaggedForDespawn()) {
                continue;
            }

            $stack = clone $entity->getItem();
            if (!$this->matchesTrackedItem($stack)) {
                continue;
            }

            $carried = $this->getCarriedItem();
            if ($carried->isNull()) {
                $this->setCarriedItem($stack);
                $entity->flagForDespawn();
                $this->getWorld()->addSound($this->getLocation(), new PopSound());
                return true;
            }

            if (!$carried->canStackWith($stack)) {
                continue;
            }

            $space = $carried->getMaxStackSize() - $carried->getCount();
            if ($space <= 0) {
                continue;
            }

            $transfer = min($space, $stack->getCount());
            if ($transfer <= 0) {
                continue;
            }

            $carried->setCount($carried->getCount() + $transfer);
            $this->setCarriedItem($carried);

            if ($transfer === $stack->getCount()) {
                $entity->flagForDespawn();
            } else {
                $remaining = $entity->getItem()->getCount() - $transfer;
                if ($remaining <= 0) {
                    $entity->flagForDespawn();
                } else {
                    $entity->setStackSize($remaining);
                }
            }

            $this->getWorld()->addSound($this->getLocation(), new PopSound());
            return true;
        }

        return false;
    }

    private function deliverItemsToOwner(Player $owner): bool
    {
        $stack = $this->getCarriedItem();
        if ($stack->isNull()) {
            return false;
        }

        $currentTick = Server::getInstance()?->getTick() ?? 0;
        if (($currentTick - $this->lastDeliveryTick) < self::DELIVERY_COOLDOWN_TICKS) {
            return false;
        }

        $this->setCarriedItem(VanillaItems::AIR());
        if ($owner->hasFiniteResources()) {
            foreach ($owner->getInventory()->addItem($stack) as $remaining) {
                $owner->dropItem($remaining);
            }
        } else {
            $owner->dropItem($stack);
        }

        $this->lastDeliveryTick = $currentTick;
        $owner->getWorld()->addSound($owner->getLocation(), new PopSound());
        return true;
    }

    private function dropCarriedItems(): void
    {
        $stack = $this->getCarriedItem();
        if ($stack->isNull()) {
            return;
        }

        $this->setCarriedItem(VanillaItems::AIR());
        $this->getWorld()->dropItem($this->getLocation()->add(0.0, 0.8, 0.0), $stack);
    }

    private function matchesTrackedItem(Item $candidate): bool
    {
        return $this->memoryItem !== null
            && !$this->memoryItem->isNull()
            && $candidate->canStackWith($this->memoryItem);
    }

    public function setOwnerName(?string $name): void
    {
        $this->ownerName = $name;
    }

    public function hasOwner(bool $onlineOnly = true): bool
    {
        if ($this->ownerName === null) {
            return false;
        }
        if (!$onlineOnly) {
            return true;
        }
        $server = Server::getInstance();
        return $server !== null && $server->getPlayerExact($this->ownerName) !== null;
    }

    public function getOwner(): ?Player
    {
        if ($this->ownerName === null) {
            return null;
        }
        $server = Server::getInstance();
        return $server?->getPlayerExact($this->ownerName);
    }

    private function setMemoryItem(?Item $item): void
    {
        if ($item === null) {
            $this->memoryItem = null;
        } else {
            $this->memoryItem = clone $item;
            $this->memoryItem->setCount(1);
        }
        $this->updateHeldItemVisual();
    }

    private function getCarriedItem(): Item
    {
        return clone $this->pouch->getItem(0);
    }

    private function setCarriedItem(Item $item): void
    {
        $this->pouch->setItem(0, clone $item);
        $this->updateHeldItemVisual();
    }

    private function updateHeldItemVisual(): void
    {
        // PocketMine Living entities (non-Human) don't expose an equipment inventory, so
        // there's currently no supported way to broadcast a held item for this mob.
        // Guarding the method prevents crashes when chunks load before custom render logic exists.
        $this->networkPropertiesDirty = true;
    }

    protected function syncNetworkData(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection $properties): void
    {
        parent::syncNetworkData($properties);
        // TODO: Add custom metadata property for carried item when bedrock protocol support is available
        // Currently there's no standard way to show held items for non-Human Living entities
    }

    private function updateBehaviourTargets(): void
    {
        if ($this->memoryItem === null || $this->memoryItem->isNull()) {
            return;
        }

        $carried = $this->getCarriedItem();
        if (!$carried->isNull()) {
            $owner = $this->getOwner();
            if ($owner !== null && $owner->isAlive()) {
                $this->steerTowards($owner->getLocation()->asVector3(), self::FOLLOW_SPEED);
            }
            return;
        }

        $nearest = $this->findNearestTrackedItemEntity();
        if ($nearest !== null) {
            $this->steerTowards($nearest->getLocation()->asVector3(), self::FOLLOW_SPEED);
            return;
        }

        $owner = $this->getOwner();
        if ($owner !== null && $owner->isAlive()) {
            $this->steerTowards($owner->getLocation()->asVector3(), self::FOLLOW_SPEED);
        }
    }

    private function maintainHoverHeight(): void
    {
        $pos = $this->getLocation();
        $world = $this->getWorld();
        $groundY = $pos->y;

        for ($y = (int)floor($pos->y); $y >= max(0, $pos->y - 10); $y--) {
            $block = $world->getBlockAt((int)floor($pos->x), $y, (int)floor($pos->z));
            if (!$block->isTransparent() || $block->isSolid()) {
                $groundY = $y + 1;
                break;
            }
        }

        $currentHeight = $pos->y - $groundY;
        if ($currentHeight < self::MIN_HOVER_HEIGHT) {
            $motion = $this->getMotion();
            $this->setMotion(new Vector3($motion->x, min($motion->y + 0.02, 0.1), $motion->z));
        } elseif ($currentHeight > self::MIN_HOVER_HEIGHT + 2.0) {
            $motion = $this->getMotion();
            $this->setMotion(new Vector3($motion->x, max($motion->y - 0.02, -0.1), $motion->z));
        }
    }

    private function steerTowards(Vector3 $target, float $speed): void
    {
        $location = $this->getLocation();
        $dx = $target->x - $location->x;
        $dy = ($target->y + 0.5) - $location->y;
        $dz = $target->z - $location->z;

        $distanceSq = $dx * $dx + $dy * $dy + $dz * $dz;
        if ($distanceSq <= self::TARGET_REACH_DISTANCE * self::TARGET_REACH_DISTANCE) {
            return;
        }

        $distance = sqrt($distanceSq);
        if ($distance <= 0.0001) {
            return;
        }

        $nx = $dx / $distance;
        $ny = $dy / $distance;
        $nz = $dz / $distance;

        $horizontal = sqrt(($nx * $nx) + ($nz * $nz));
        $this->setRotation(
            rad2deg(atan2(-$nx, $nz)),
            rad2deg(-atan2($ny, $horizontal > 0 ? $horizontal : 1.0))
        );

        $this->setMotion(new Vector3(
            $nx * $speed,
            max(min($ny * $speed, 0.15), -0.15),
            $nz * $speed
        ));
        $this->setForceMovementUpdate();
    }

    private function findNearestTrackedItemEntity(): ?ItemEntity
    {
        $bb = $this->getBoundingBox()->expandedCopy(self::PICKUP_RADIUS, 4.0, self::PICKUP_RADIUS);
        $nearest = null;
        $nearestDist = PHP_FLOAT_MAX;

        foreach ($this->getWorld()->getNearbyEntities($bb, $this) as $entity) {
            if (!$entity instanceof ItemEntity || $entity->isFlaggedForDespawn()) {
                continue;
            }

            $item = $entity->getItem();
            if (!$this->matchesTrackedItem($item)) {
                continue;
            }

            $distance = $entity->getLocation()->distanceSquared($this->getLocation());
            if ($distance < $nearestDist) {
                $nearestDist = $distance;
                $nearest = $entity;
            }
        }

        return $nearest;
    }
}
