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

namespace pocketmine\entity\projectile;

use pocketmine\entity\animation\CrossbowFireworkParticlesAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\FireworkRocket as FireworkItem;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\world\sound\FireworkCrackleSound;
use pocketmine\world\sound\FireworkExplosionSound;
use pocketmine\world\sound\FireworkLaunchSound;
use function count;
use function sqrt;

final class CrossbowFirework extends Projectile implements NeverSavedWithChunkEntity{

	public static function getNetworkTypeId() : string{ return EntityIds::FIREWORKS_ROCKET; }

	/** @var FireworkRocketExplosion[] */
	private array $explosions = [];

	public function __construct(
		Location $location,
		?Entity $shootingEntity,
		private int $maxFlightTimeTicks,
		array $explosions,
		?CompoundTag $nbt = null
	){
		$this->setExplosions($explosions);
		parent::__construct($location, $shootingEntity, $nbt);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.25, 0.25); }

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravity() : float{ return 0.0; }

	/**
	 * @param FireworkRocketExplosion[] $explosions
	 */
	public function setExplosions(array $explosions) : void{
		$this->explosions = $explosions;
	}

	protected function onFirstUpdate(int $currentTick) : void{
		parent::onFirstUpdate($currentTick);
		$this->broadcastSound(new FireworkLaunchSound());
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isFlaggedForDespawn()){
			if($this->ticksLived < 60){
				$this->addMotion($this->motion->x * 0.15, 0.04, $this->motion->z * 0.15);
			}

			if($this->ticksLived >= $this->maxFlightTimeTicks){
				$this->explode();
				$this->flagForDespawn();
			}
		}

		return $hasUpdate;
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		$this->explode();
		$this->flagForDespawn();
	}

	protected function onHitEntity(Entity $entityHit, \pocketmine\math\RayTraceResult $hitResult) : void{
		// Explosion damage is handled by explode().
	}

	public function explode() : void{
		$this->broadcastAnimation(new CrossbowFireworkParticlesAnimation($this));

		if(($explosionCount = count($this->explosions)) !== 0){
			foreach($this->explosions as $explosion){
				$this->broadcastSound($explosion->getType()->getExplosionSound());
				if($explosion->willTwinkle()){
					$this->broadcastSound(new FireworkCrackleSound());
				}
			}

			$force = ($explosionCount * 2) + 5;
			$world = $this->getWorld();
			foreach($world->getCollidingEntities($this->getBoundingBox()->expandedCopy(5, 5, 5), $this) as $entity){
				if(!$entity instanceof Living){
					continue;
				}

				$position = $entity->getPosition();
				$distance = $position->distanceSquared($this->location);
				if($distance > 25){
					continue;
				}

				$height = $entity->getBoundingBox()->getYLength();
				for($i = 0; $i < 2; $i++){
					$target = $position->add(0, 0.5 * $i * $height, 0);
					foreach(VoxelRayTrace::betweenPoints($this->location, $target) as $blockPos){
						if($world->getBlock($blockPos)->calculateIntercept($this->location, $target) !== null){
							continue 2;
						}
					}

					$damage = $force * sqrt((5 - $position->distance($this->location)) / 5);
					$ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, $damage);
					$entity->attack($ev);
					break;
				}
			}
		}else{
			$this->broadcastSound(new FireworkExplosionSound());
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$explosions = new ListTag();
		foreach($this->explosions as $explosion){
			$explosions->push($explosion->toCompoundTag());
		}

		$fireworksData = CompoundTag::create()
			->setTag(FireworkItem::TAG_FIREWORK_DATA, CompoundTag::create()
				->setTag(FireworkItem::TAG_EXPLOSIONS, $explosions)
			);

		$properties->setCompoundTag(EntityMetadataProperties::FIREWORK_ITEM, new CacheableNbt($fireworksData));
	}
}
