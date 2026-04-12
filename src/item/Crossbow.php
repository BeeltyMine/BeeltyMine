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

namespace pocketmine\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\CrossbowFirework;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootCrossbowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\sound\CrossbowLoadSound;
use pocketmine\world\sound\CrossbowShootSound;
use function cos;
use function count;
use function deg2rad;
use function max;
use function mt_rand;
use function sin;

class Crossbow extends Tool implements Chargeable{

	private const TAG_CHARGED_ITEM = "chargedItem"; // TAG_Compound

	private ?Item $chargedItem = null;

	private function isSupportedAmmo(Item $item) : bool{
		return $item instanceof Arrow || $item instanceof FireworkRocket;
	}

	/**
	 * @return array{Inventory, Item}|null
	 */
	private function findAmmo(Player $player) : ?array{
		$offHand = $player->getOffHandInventory()->getItem(0);
		if($this->isSupportedAmmo($offHand)){
			return [$player->getOffHandInventory(), $offHand];
		}

		foreach($player->getInventory()->getContents() as $slotItem){
			if($this->isSupportedAmmo($slotItem)){
				return [$player->getInventory(), $slotItem];
			}
		}

		return null;
	}

	public function continueUsing(Player $player, int $useDuration) : bool{
		$quickCharge = $this->getEnchantmentLevel(VanillaEnchantments::QUICK_CHARGE());
		if($useDuration <= 1){
			$player->getWorld()->addSound($player->getPosition(), new CrossbowLoadSound(CrossbowLoadSound::LOADING_START, $quickCharge > 0));
			return false;
		}
		$chargeDuration = $this->getChargeDuration();
		$multiplier = 25.0 / $chargeDuration;
		$adjustedTicks = (int) ($useDuration * $multiplier);
		if($adjustedTicks % 16 === 0){
			$player->getWorld()->addSound($player->getPosition(), new CrossbowLoadSound(CrossbowLoadSound::LOADING_MIDDLE, $quickCharge > 0));
			return false;
		}
		if($useDuration >= $chargeDuration){
			$player->getWorld()->addSound($player->getPosition(), new CrossbowLoadSound(CrossbowLoadSound::LOADING_END, $quickCharge > 0));

			if($this->chargedItem === null){
				$ammo = $this->findAmmo($player);
				if($player->hasFiniteResources()){
					if($ammo === null){
						return false;
					}
					[$inventory, $loadedItem] = $ammo;
					$loadedItem = clone $loadedItem;
					$loadedItem->setCount(1);
					$inventory->removeItem($loadedItem);
					$this->setCharged($loadedItem);
				}else{
					$loadedItem = $ammo !== null ? clone $ammo[1] : VanillaItems::ARROW();
					$loadedItem->setCount(1);
					$this->setCharged($loadedItem);
				}
			}

			return true;
		}
		return false;
	}

	public function canStartUsingItem(Player $player) : bool{
		return !$player->hasFiniteResources() || $this->findAmmo($player) !== null;
	}

	public function getMaxDurability() : int{
		return 466;
	}

	public function setCharged(?Item $item) : void{
		$this->chargedItem = $item !== null ? clone $item : null;
	}

	public function isCharged() : bool{
		return $this->chargedItem !== null;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		if($this->chargedItem === null){
			return ItemUseResult::NONE;
		}
		$location = $player->getLocation();
		$entities = [];
		if($this->chargedItem instanceof FireworkRocket){
			$randomDuration = (($this->chargedItem->getFlightTimeMultiplier() + 1) * 10) + mt_rand(0, 12);
			$entity = new CrossbowFirework(
				Location::fromObject(
					$player->getEyePos(),
					$player->getWorld(),
					($location->yaw > 180 ? 360 : 0) - $location->yaw,
					-$location->pitch
				),
				$player,
				$randomDuration,
				$this->chargedItem->getExplosions()
			);
			$entity->setMotion($player->getDirectionVector()->multiply(1.6));
			$entities[] = $entity;
		}elseif($this->hasEnchantment(VanillaEnchantments::MULTISHOT())){
			$yawOffsets = [-10, 0, 10];
			foreach($yawOffsets as $i => $yawOffset){
				$arrowYaw = $location->yaw + $yawOffset;
				$arrowPitch = $location->pitch;

				$entity = new ArrowEntity(Location::fromObject(
					$player->getEyePos(),
					$player->getWorld(),
					($arrowYaw > 180 ? 360 : 0) - $arrowYaw,
					-$arrowPitch
				), $player, true);
				$entity->setOwningEntity($player);

				if($i !== 1 || !$player->hasFiniteResources()){
					$entity->setPickupMode(ArrowEntity::PICKUP_CREATIVE);
				}

				$yawRad = deg2rad($arrowYaw);
				$pitchRad = deg2rad($arrowPitch);
				$y = -sin($pitchRad);
				$xz = cos($pitchRad);
				$x = -$xz * sin($yawRad);
				$z = $xz * cos($yawRad);

				$directionVector = (new Vector3($x, $y, $z))->normalize();
				$entity->setMotion($directionVector->multiply(3.15));

				$entities[] = $entity;
			}
		}else{
			$entity = new ArrowEntity(Location::fromObject(
				$player->getEyePos(),
				$player->getWorld(),
				($location->yaw > 180 ? 360 : 0) - $location->yaw,
				-$location->pitch
			), $player, true);
			$entity->setMotion($player->getDirectionVector()->multiply(3.15));
			$entity->setOwningEntity($player);
			$entity->setPickupMode($player->hasFiniteResources() ? ArrowEntity::PICKUP_ANY : ArrowEntity::PICKUP_CREATIVE);
			$entities[] = $entity;
		}
		if(count($entities) === 0){
			throw new AssumptionFailedError("This should never happen");
		}
		$ev = new EntityShootCrossbowEvent($player, $this, $entities);
		$ev->call();
		if($ev->isCancelled()){
			foreach($ev->getProjectiles() as $projectile){
				$projectile->flagForDespawn();
			}
			return ItemUseResult::FAIL;
		}
		foreach($ev->getProjectiles() as $projectile){
			if($projectile instanceof Projectile){
				$launchEv = new ProjectileLaunchEvent($projectile);
				$launchEv->call();
				if($launchEv->isCancelled()){
					$projectile->flagForDespawn();
					return ItemUseResult::FAIL;
				}
				$projectile->spawnToAll();
			}
		}
		$player->getWorld()->addSound($player->getPosition(), new CrossbowShootSound());
		if($player->hasFiniteResources()){
			$this->applyDamage($this->hasEnchantment(VanillaEnchantments::MULTISHOT()) ? 3 : 1);
		}
		$this->chargedItem = null;
		return ItemUseResult::SUCCESS;
	}

	public function getChargeDuration() : int{
		$quickChargeLevel = $this->getEnchantmentLevel(VanillaEnchantments::QUICK_CHARGE());
		return max(1, 25 - (5 * $quickChargeLevel));
	}

	protected function serializeCompoundTag(CompoundTag $tag) : void{
		parent::serializeCompoundTag($tag);
		if($this->chargedItem !== null){
			$tag->setTag(self::TAG_CHARGED_ITEM, $this->chargedItem->nbtSerialize(-1));
		}else{
			$tag->removeTag(self::TAG_CHARGED_ITEM);
		}
	}

	protected function deserializeCompoundTag(CompoundTag $tag) : void{
		parent::deserializeCompoundTag($tag);
		if(($chargedItemTag = $tag->getCompoundTag(self::TAG_CHARGED_ITEM)) !== null){
			$this->chargedItem = Item::nbtDeserialize($chargedItemTag);
		}
	}

	public function getFuelTime() : int{
		return 300;
	}
}
