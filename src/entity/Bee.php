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
 * @author bonbionTR
 * @team BeeltyMine
 * 
 * 
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\Beehive;
use pocketmine\block\BeeNest;
use pocketmine\block\DoublePlant;
use pocketmine\block\Flower;
use pocketmine\block\tile\Beehive as TileBeehive;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\SpawnEgg;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\entity\animation\BeeStingAnimation;
use pocketmine\world\sound\BeehiveEnterSound;
use pocketmine\world\sound\BeeStingSound;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\World;

use function abs;
use function atan2;
use function max;
use function min;
use function mt_rand;
use function sqrt;
use const M_PI;

class Bee extends Living implements Ageable{

	private static array $occupiedFlowers = [];

	private static function flowerKey(int $x, int $y, int $z) : string{
		return "$x:$y:$z";
	}

	private static function claimFlower(int $x, int $y, int $z, int $entityId) : bool{
		$key = self::flowerKey($x, $y, $z);
		if(isset(self::$occupiedFlowers[$key]) && self::$occupiedFlowers[$key] !== $entityId){
			return false;
		}
		self::$occupiedFlowers[$key] = $entityId;
		return true;
	}

	private static function releaseFlower(int $x, int $y, int $z, int $entityId) : void{
		$key = self::flowerKey($x, $y, $z);
		if(isset(self::$occupiedFlowers[$key]) && self::$occupiedFlowers[$key] === $entityId){
			unset(self::$occupiedFlowers[$key]);
		}
	}

	private static function isFlowerOccupied(int $x, int $y, int $z) : bool{
		return isset(self::$occupiedFlowers[self::flowerKey($x, $y, $z)]);
	}

	private const TAG_HAS_NECTAR = "HasNectar";
	private const TAG_PROPERTIES = "properties";
	private const TAG_PROPERTY_HAS_NECTAR = "minecraft:has_nectar";
	private const TAG_HOME_X = "HomeX";
	private const TAG_HOME_Y = "HomeY";
	private const TAG_HOME_Z = "HomeZ";
	private const TAG_HAS_STUNG = "HasStung";
	private const TAG_ANGER_TIME = "AngerTime";
	private const TAG_BABY = "Baby";
	private const TAG_AGE = "Age";
	private const TAG_LOVE_COOLDOWN = "LoveCooldown";

	private const FLOWER_SEARCH_INTERVAL = 80;
	private const HIVE_SEARCH_INTERVAL = 100;
	private const FLOWER_SEARCH_RADIUS = 8;
	private const HIVE_SEARCH_RADIUS = 16;
	private const SEARCH_VERTICAL_RANGE = 6;
	private const FLOWER_SEARCH_SAMPLES = 72;
	private const HIVE_SEARCH_SAMPLES = 96;
	private const POLLINATE_TICKS_REQUIRED = 400;
	private const FLOWER_REACH_SQ = 2.25;
	private const HIVE_REACH_SQ = 3.24;
	private const MAX_WANDER_DISTANCE_SQ = 484;

	private const FLY_SPEED = 0.10;
	private const FLY_SPEED_NECTAR = 0.08;
	private const FLY_SPEED_ANGRY = 0.16;
	private const FLY_GRAVITY_OFFSET = 0.04;
	private const FLY_DIRECTION_BLEND = 0.25;
	private const FLY_WANDER_INTERVAL = 80;
	private const FLY_HOVER_Y_VARIANCE = 0.01;
	private const FLY_HOVER_MIN_Y = 1.0;
	private const FLY_HOVER_MAX_Y = 4.0;
	private const FLY_MAX_SPEED = 0.12;
	private const FLY_MAX_Y_SPEED = 0.06;
	private const FLY_MAX_Y_SPEED_ANGRY = 0.14;

	private const ANGER_DURATION_TICKS = 500;
	private const STING_RANGE_SQ = 2.25;
	private const STING_DAMAGE = 2.0;
	private const STING_POISON_DURATION = 200;
	private const STING_DEATH_DELAY = 1100;
	private const NEARBY_BEE_ALERT_RADIUS = 16;

	private const SEPARATION_RADIUS = 1.5;
	private const SEPARATION_STRENGTH = 0.15;
	private const SEPARATION_UPDATE_INTERVAL = 10;
	private const SEPARATION_MAX_NEIGHBORS = 8;
	private const GROUND_CHECK_INTERVAL = 16;

	private const HEART_PARTICLE_INTERVAL = 10;
	private const FOLLOW_SCAN_INTERVAL = 12;
	private const BREED_SCAN_INTERVAL = 12;
	private const BABY_FOLLOW_SCAN_INTERVAL = 12;

	private const LOVE_MODE_DURATION = 600;
	private const BREED_COOLDOWN = 6000;
	private const BREED_RANGE_SQ = 3.0;
	private const BREED_XP_MIN = 1;
	private const BREED_XP_MAX = 7;
	private const BABY_GROW_TICKS = 24000;
	private const BABY_SCALE = 0.5;
	private const FOLLOW_FLOWER_RANGE = 8.0;
	private const FOLLOW_SPEED = 0.10;
	private const FOLLOW_MIN_DISTANCE = 1.25;
	private const FOLLOW_MIN_DISTANCE_SQ = 1.5625;
	private const FOLLOW_HOLD_DISTANCE_SQ = 3.24;
	private const FOLLOW_TARGET_OFFSET = 1.4;

	public static function getNetworkTypeId() : string{ return EntityIds::BEE; }

	protected bool $baby = false;
	private bool $hasNectar = false;
	private bool $hasStung = false;
	private bool $angry = false;

	private int $pollinateTicks = 0;
	private int $targetSearchCooldown = 0;
	private int $angerTicks = 0;
	private int $stingDeathTicks = 0;
	private int $wanderTicker = 0;

	private int $ageTicks = 0;
	private int $loveTicks = 0;
	private int $loveCooldown = 0;
	private bool $inLove = false;
	private int $heartParticleTicker = 0;

	private Vector3 $desiredDirection;
	private float $currentSpeed = self::FLY_SPEED;
	private float $hoverTargetY;

	private bool $isPollinating = false;
	private bool $hasActiveTarget = false;
	private float $activeTargetY = 0.0;
	private int $groundCheckTicker = 0;
	private float $cachedGroundY = 0.0;
	private int $separationTicker = 0;
	private float $cachedSepX = 0.0;
	private float $cachedSepZ = 0.0;
	private int $followScanCooldown = 0;
	private ?Player $cachedFlowerHolder = null;
	private int $breedScanCooldown = 0;
	private ?self $cachedBreedMate = null;
	private int $babyFollowScanCooldown = 0;
	private ?self $cachedBabyLeader = null;

	private ?Vector3 $flowerTarget = null;
	private ?Vector3 $homeHive = null;
	private int $hiveEntryCooldown = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.5, 0.55);
	}

	protected function getInitialGravity() : float{
		return 0.04;
	}

	protected function getInitialDragMultiplier() : float{
		return 0.02;
	}

	protected function calculateFallDamage(float $fallDistance) : float{
		return 0;
	}

	public function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(10);
		parent::initEntity($nbt);

		$this->desiredDirection = $this->generateRandomDirection();
		$this->hoverTargetY = self::FLY_HOVER_MIN_Y + (mt_rand(0, 3000) / 1000.0) * (self::FLY_HOVER_MAX_Y - self::FLY_HOVER_MIN_Y);
		$this->activeTargetY = $this->location->y;
		$this->cachedGroundY = $this->location->y;
		$this->targetSearchCooldown = mt_rand(0, self::FLOWER_SEARCH_INTERVAL);
		$this->groundCheckTicker = mt_rand(0, self::GROUND_CHECK_INTERVAL);
		$this->separationTicker = mt_rand(0, self::SEPARATION_UPDATE_INTERVAL);
		$this->followScanCooldown = mt_rand(0, self::FOLLOW_SCAN_INTERVAL);
		$this->breedScanCooldown = mt_rand(0, self::BREED_SCAN_INTERVAL);
		$this->babyFollowScanCooldown = mt_rand(0, self::BABY_FOLLOW_SCAN_INTERVAL);
		$properties = $nbt->getCompoundTag(self::TAG_PROPERTIES);
		if($properties !== null && $properties->getTag(self::TAG_PROPERTY_HAS_NECTAR) !== null){
			$this->hasNectar = $properties->getByte(self::TAG_PROPERTY_HAS_NECTAR, 0) !== 0;
		}else{
			$this->hasNectar = $nbt->getByte(self::TAG_HAS_NECTAR, 0) !== 0;
		}
		$this->hasStung = $nbt->getByte(self::TAG_HAS_STUNG, 0) !== 0;
		$this->angerTicks = $nbt->getInt(self::TAG_ANGER_TIME, 0);
		$this->angry = $this->angerTicks > 0 && !$this->hasStung;
		$this->currentSpeed = $this->hasNectar ? self::FLY_SPEED_NECTAR : self::FLY_SPEED;
		$this->baby = $nbt->getByte(self::TAG_BABY, 0) !== 0;
		$this->ageTicks = $nbt->getInt(self::TAG_AGE, 0);
		$this->loveCooldown = $nbt->getInt(self::TAG_LOVE_COOLDOWN, 0);

		if($this->baby){
			$this->setScale(self::BABY_SCALE);
		}

		if(
			($nbt->getTag(self::TAG_HOME_X) instanceof IntTag) &&
			($nbt->getTag(self::TAG_HOME_Y) instanceof IntTag) &&
			($nbt->getTag(self::TAG_HOME_Z) instanceof IntTag)
		){
			$this->homeHive = new Vector3(
				$nbt->getInt(self::TAG_HOME_X),
				$nbt->getInt(self::TAG_HOME_Y),
				$nbt->getInt(self::TAG_HOME_Z)
			);
			if(!$this->hasNectar){
				$this->hiveEntryCooldown = 200;
			}
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_HAS_NECTAR, $this->hasNectar ? 1 : 0);
		$nbt->setByte(self::TAG_HAS_STUNG, $this->hasStung ? 1 : 0);
		$nbt->setInt(self::TAG_ANGER_TIME, $this->angerTicks);
		$nbt->setByte(self::TAG_BABY, $this->baby ? 1 : 0);
		$nbt->setInt(self::TAG_AGE, $this->ageTicks);
		$nbt->setInt(self::TAG_LOVE_COOLDOWN, $this->loveCooldown);
		$nbt->setTag(
			self::TAG_PROPERTIES,
			CompoundTag::create()->setByte(self::TAG_PROPERTY_HAS_NECTAR, $this->hasNectar ? 1 : 0)
		);

		if($this->homeHive !== null){
			$nbt->setInt(self::TAG_HOME_X, (int) $this->homeHive->x);
			$nbt->setInt(self::TAG_HOME_Y, (int) $this->homeHive->y);
			$nbt->setInt(self::TAG_HOME_Z, (int) $this->homeHive->z);
		}

		return $nbt;
	}

	public function getName() : string{
		return "Bee";
	}

	public function isBaby() : bool{
		return $this->baby;
	}

	public function setBaby(bool $baby = true) : void{
		$this->baby = $baby;
		$this->setScale($baby ? self::BABY_SCALE : 1.0);
		$this->networkPropertiesDirty = true;
	}

	public function hasNectar() : bool{
		return $this->hasNectar;
	}

	public function isInLove() : bool{
		return $this->inLove;
	}

	private static function isBreedingFlower(Item $item) : bool{
		$block = $item->getBlock();
		return $block instanceof Flower || $block instanceof DoublePlant;
	}

	private static function isPlayerHoldingBreedingFlower(Player $player) : bool{
		if(!$player->isConnected() || !$player->isAlive() || $player->isSpectator()){
			return false;
		}
		try{
			$item = $player->getInventory()->getItemInHand();
		}catch(\Error){
			return false;
		}
		return self::isBreedingFlower($item);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($item instanceof SpawnEgg){
			$baby = new self(Location::fromObject($this->location->add(0, 0.5, 0), $this->getWorld(), mt_rand(0, 360), 0));
			$baby->setBaby();
			$baby->spawnToAll();
			if(!$player->isCreative()){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
			}
			return true;
		}

		if(!$this->baby && !$this->inLove && $this->loveCooldown <= 0 && self::isBreedingFlower($item)){
			$this->inLove = true;
			$this->loveTicks = self::LOVE_MODE_DURATION;
			$this->heartParticleTicker = 0;
			$this->networkPropertiesDirty = true;
			$this->getWorld()->addParticle(
				$this->location->add(0, $this->getSize()->getHeight() + 0.2, 0),
				new HeartParticle()
			);
			if(!$player->isCreative()){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
			}
			return true;
		}

		if($this->baby && self::isBreedingFlower($item)){
			$this->ageTicks = min($this->ageTicks + (int) (self::BABY_GROW_TICKS * 0.1), self::BABY_GROW_TICKS);
			if($this->ageTicks >= self::BABY_GROW_TICKS){
				$this->setBaby(false);
				$this->ageTicks = 0;
			}
			$this->getWorld()->broadcastPacketToViewers(
				$this->location,
				ActorEventPacket::create($this->getId(), ActorEvent::BABY_ANIMAL_FEED, 0)
			);
			if(!$player->isCreative()){
				$item->pop();
				$player->getInventory()->setItemInHand($item);
			}
			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	private function generateRandomDirection() : Vector3{
		if($this->homeHive !== null){
			$dx = $this->homeHive->x + 0.5 - $this->location->x;
			$dz = $this->homeHive->z + 0.5 - $this->location->z;
			$distSq = $dx * $dx + $dz * $dz;
			if($distSq > self::MAX_WANDER_DISTANCE_SQ * 0.6){
				return (new Vector3($dx, mt_rand(-50, 50) / 1000, $dz))->normalize();
			}
		}
		return new Vector3(
			mt_rand(-1000, 1000) / 1000,
			mt_rand(-50, 50) / 1000,
			mt_rand(-1000, 1000) / 1000
		);
	}

	private function distanceSqTo(Vector3 $pos) : float{
		return (($this->location->x - $pos->x) ** 2) + (($this->location->y - $pos->y) ** 2) + (($this->location->z - $pos->z) ** 2);
	}

	private function blockCenter(Vector3 $pos) : Vector3{
		return new Vector3($pos->x + 0.5, $pos->y + 0.5, $pos->z + 0.5);
	}

	private function blendDirection(Vector3 $desired) : void{
		$len = $desired->lengthSquared();
		if($len <= 0.0001){
			return;
		}
		$target = $desired->normalize();
		$this->desiredDirection = new Vector3(
			$this->desiredDirection->x + ($target->x - $this->desiredDirection->x) * self::FLY_DIRECTION_BLEND,
			$this->desiredDirection->y + ($target->y - $this->desiredDirection->y) * self::FLY_DIRECTION_BLEND,
			$this->desiredDirection->z + ($target->z - $this->desiredDirection->z) * self::FLY_DIRECTION_BLEND
		);
		$nLen = $this->desiredDirection->lengthSquared();
		if($nLen > 0.0001){
			$this->desiredDirection = $this->desiredDirection->normalize();
		}
	}

	private function steerTowards(Vector3 $target) : void{
		$this->hasActiveTarget = true;
		$this->activeTargetY = $target->y;
		$this->blendDirection($target->subtractVector($this->location));
	}

	private function isPollinableFlower(Vector3 $pos) : bool{
		$block = $this->getWorld()->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z);
		return $block instanceof Flower || ($block instanceof DoublePlant && !$block->isTop());
	}

	private function findNearestFlower() : ?Vector3{
		$world = $this->getWorld();
		$bx = (int) $this->location->x;
		$by = (int) $this->location->y;
		$bz = (int) $this->location->z;
		$yMin = max($world->getMinY(), $by - self::SEARCH_VERTICAL_RANGE);
		$yMax = min($world->getMaxY() - 1, $by + self::SEARCH_VERTICAL_RANGE);

		$best = null;
		$bestDist = PHP_INT_MAX;

		$scanYLow = max($yMin, $by - 2);
		$scanYHigh = min($yMax, $by + 2);
		for($scanY = $scanYLow; $scanY <= $scanYHigh; ++$scanY){
			for($x = $bx - self::FLOWER_SEARCH_RADIUS; $x <= $bx + self::FLOWER_SEARCH_RADIUS; ++$x){
				for($z = $bz - self::FLOWER_SEARCH_RADIUS; $z <= $bz + self::FLOWER_SEARCH_RADIUS; ++$z){
					$block = $world->getBlockAt($x, $scanY, $z);
					if(!($block instanceof Flower) && !($block instanceof DoublePlant && !$block->isTop())){
						continue;
					}
					if(self::isFlowerOccupied($x, $scanY, $z)){
						continue;
					}
					$c = $this->blockCenter(new Vector3($x, $scanY, $z));
					$d = $this->distanceSqTo($c);
					if($d < $bestDist){
						$bestDist = $d;
						$best = new Vector3($x, $scanY, $z);
					}
				}
			}
		}
		if($best !== null){
			return $best;
		}

		for($i = 0; $i < self::FLOWER_SEARCH_SAMPLES; ++$i){
			$x = $bx + mt_rand(-self::FLOWER_SEARCH_RADIUS, self::FLOWER_SEARCH_RADIUS);
			$z = $bz + mt_rand(-self::FLOWER_SEARCH_RADIUS, self::FLOWER_SEARCH_RADIUS);
			$y = mt_rand($yMin, $yMax);

			$block = $world->getBlockAt($x, $y, $z);
			if(!($block instanceof Flower) && !($block instanceof DoublePlant && !$block->isTop())){
				continue;
			}
			if(self::isFlowerOccupied($x, $y, $z)){
				continue;
			}
			$c = $this->blockCenter(new Vector3($x, $y, $z));
			$d = $this->distanceSqTo($c);
			if($d < $bestDist){
				$bestDist = $d;
				$best = new Vector3($x, $y, $z);
			}
		}
		return $best;
	}

	private function isHiveBlock(Vector3 $pos) : bool{
		$block = $this->getWorld()->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z);
		return $block instanceof Beehive || $block instanceof BeeNest;
	}

	private function findNearestHive() : ?Vector3{
		$world = $this->getWorld();
		$bx = (int) $this->location->x;
		$by = (int) $this->location->y;
		$bz = (int) $this->location->z;
		$yMin = max($world->getMinY(), $by - self::SEARCH_VERTICAL_RANGE);
		$yMax = min($world->getMaxY() - 1, $by + self::SEARCH_VERTICAL_RANGE);

		$best = null;
		$bestDist = PHP_INT_MAX;

		$scanYLow = max($yMin, $by - 4);
		$scanYHigh = min($yMax, $by + 4);
		for($scanY = $scanYLow; $scanY <= $scanYHigh; ++$scanY){
			for($x = $bx - self::HIVE_SEARCH_RADIUS; $x <= $bx + self::HIVE_SEARCH_RADIUS; $x += 2){
				for($z = $bz - self::HIVE_SEARCH_RADIUS; $z <= $bz + self::HIVE_SEARCH_RADIUS; $z += 2){
					$block = $world->getBlockAt($x, $scanY, $z);
					if(!($block instanceof Beehive) && !($block instanceof BeeNest)){
						continue;
					}
					$tile = $world->getTile(new Vector3($x, $scanY, $z));
					if($tile instanceof TileBeehive && $tile->isFull()){
						continue;
					}
					$c = $this->blockCenter(new Vector3($x, $scanY, $z));
					$d = $this->distanceSqTo($c);
					if($d < $bestDist){
						$bestDist = $d;
						$best = new Vector3($x, $scanY, $z);
					}
				}
			}
		}
		if($best !== null){
			return $best;
		}

		for($i = 0; $i < self::HIVE_SEARCH_SAMPLES; ++$i){
			$x = $bx + mt_rand(-self::HIVE_SEARCH_RADIUS, self::HIVE_SEARCH_RADIUS);
			$z = $bz + mt_rand(-self::HIVE_SEARCH_RADIUS, self::HIVE_SEARCH_RADIUS);
			$y = mt_rand($yMin, $yMax);

			$block = $world->getBlockAt($x, $y, $z);
			if(!($block instanceof Beehive) && !($block instanceof BeeNest)){
				continue;
			}
			$tile = $world->getTile(new Vector3($x, $y, $z));
			if($tile instanceof TileBeehive && $tile->isFull()){
				continue;
			}
			$c = $this->blockCenter(new Vector3($x, $y, $z));
			$d = $this->distanceSqTo($c);
			if($d < $bestDist){
				$bestDist = $d;
				$best = new Vector3($x, $y, $z);
			}
		}
		return $best;
	}

	private function tryPollinate(int $tickDiff) : void{
		if($this->flowerTarget === null){
			$this->isPollinating = false;
			return;
		}
		if(!$this->isPollinableFlower($this->flowerTarget)){
			self::releaseFlower((int) $this->flowerTarget->x, (int) $this->flowerTarget->y, (int) $this->flowerTarget->z, $this->getId());
			$this->flowerTarget = null;
			$this->pollinateTicks = 0;
			$this->isPollinating = false;
			return;
		}

		if(!self::claimFlower((int) $this->flowerTarget->x, (int) $this->flowerTarget->y, (int) $this->flowerTarget->z, $this->getId())){
			$this->flowerTarget = null;
			$this->pollinateTicks = 0;
			$this->isPollinating = false;
			return;
		}

		$hoverPos = new Vector3($this->flowerTarget->x + 0.5, $this->flowerTarget->y + 0.8, $this->flowerTarget->z + 0.5);
		$distSq = $this->distanceSqTo($hoverPos);

		if($distSq > self::FLOWER_REACH_SQ){
			$this->steerTowards($hoverPos);
			$this->currentSpeed = self::FLY_SPEED;
			$this->pollinateTicks = 0;
			$this->isPollinating = false;
			return;
		}

		$this->isPollinating = true;
		$this->hasActiveTarget = false;
		$this->desiredDirection = new Vector3(0.0, 0.0, 0.0);
		$this->currentSpeed = 0.0;
		$dx = $hoverPos->x - $this->location->x;
		$dy = $hoverPos->y - $this->location->y;
		$dz = $hoverPos->z - $this->location->z;
		$this->motion = new Vector3(
			$dx * 0.2,
			$dy * 0.2,
			$dz * 0.2
		);

		$this->pollinateTicks += $tickDiff;
		if($this->pollinateTicks >= self::POLLINATE_TICKS_REQUIRED){
			$this->setHasNectar(true);
			$this->pollinateTicks = 0;
			self::releaseFlower((int) $this->flowerTarget->x, (int) $this->flowerTarget->y, (int) $this->flowerTarget->z, $this->getId());
			$this->flowerTarget = null;
			$this->isPollinating = false;
			$this->targetSearchCooldown = 0;
			$this->currentSpeed = self::FLY_SPEED_NECTAR;
		}
	}

	private function setHasNectar(bool $nectar) : void{
		if($this->hasNectar === $nectar){
			return;
		}
		$this->hasNectar = $nectar;
		$this->entityPropertiesDirty = true;
		$this->networkPropertiesDirty = true;
	}

	private function tryDepositNectar() : void{
		if($this->homeHive === null){
			return;
		}
		if(!$this->isHiveBlock($this->homeHive)){
			$this->homeHive = null;
			$this->targetSearchCooldown = 0;
			return;
		}

		$center = $this->blockCenter($this->homeHive);
		$this->steerTowards($center);
		$this->currentSpeed = self::FLY_SPEED_NECTAR;

		if($this->distanceSqTo($center) > self::HIVE_REACH_SQ){
			return;
		}

		$world = $this->getWorld();

		$tile = $world->getTile($this->homeHive);
		if($tile instanceof TileBeehive && !$tile->isFull()){
			$tile->addBee($this->saveNBT(), $this->hasNectar);
			$world->addSound($center, new BeehiveEnterSound());
			$this->flagForDespawn();
			return;
		}

		$this->homeHive = null;
		$this->desiredDirection = $this->generateRandomDirection();
		$this->targetSearchCooldown = self::HIVE_SEARCH_INTERVAL;
	}

	private function isNightTime() : bool{
		$time = $this->getWorld()->getTimeOfDay();
		return $time >= World::TIME_SUNSET && $time < World::TIME_SUNRISE;
	}

	private function tickGathering(int $tickDiff) : void{
		if($this->targetSearchCooldown > 0){
			$this->targetSearchCooldown = max(0, $this->targetSearchCooldown - $tickDiff);
		}
		if($this->hiveEntryCooldown > 0){
			$this->hiveEntryCooldown = max(0, $this->hiveEntryCooldown - $tickDiff);
		}

		$isNight = $this->isNightTime();
		$shouldSearchHive = $this->hasNectar || $isNight;

		if($shouldSearchHive && $this->hiveEntryCooldown <= 0){
			if($isNight && $this->flowerTarget !== null){
				self::releaseFlower((int) $this->flowerTarget->x, (int) $this->flowerTarget->y, (int) $this->flowerTarget->z, $this->getId());
				$this->flowerTarget = null;
				$this->pollinateTicks = 0;
				$this->isPollinating = false;
			}
			$this->currentSpeed = self::FLY_SPEED_NECTAR;
			if($this->homeHive === null || !$this->isHiveBlock($this->homeHive)){
				$this->homeHive = null;
				if($this->targetSearchCooldown === 0){
					$found = $this->findNearestHive();
					$this->homeHive = $found;
					$this->targetSearchCooldown = $found !== null ? self::HIVE_SEARCH_INTERVAL : (int) (self::HIVE_SEARCH_INTERVAL * 0.4);
				}
			}
			$this->tryDepositNectar();
			return;
		}

		$this->currentSpeed = self::FLY_SPEED;
		if($this->flowerTarget === null || !$this->isPollinableFlower($this->flowerTarget)){
			if($this->flowerTarget !== null){
				self::releaseFlower((int) $this->flowerTarget->x, (int) $this->flowerTarget->y, (int) $this->flowerTarget->z, $this->getId());
				$this->flowerTarget = null;
				$this->isPollinating = false;
			}
			if($this->targetSearchCooldown === 0){
				$found = $this->findNearestFlower();
				$this->flowerTarget = $found;
				$this->targetSearchCooldown = $found !== null ? self::FLOWER_SEARCH_INTERVAL : (int) (self::FLOWER_SEARCH_INTERVAL * 0.4);
			}
		}
		$this->tryPollinate($tickDiff);
	}

	public function setAngry(Entity $target) : void{
		if($this->hasStung || !$this->isAlive()){
			return;
		}
		if($target instanceof Player && $target->isCreative()){
			return;
		}
		$this->setTargetEntity($target);
		$this->angerTicks = self::ANGER_DURATION_TICKS;
		$this->angry = true;
		$this->networkPropertiesDirty = true;
	}

	public static function alertNearbyBees(Entity $target, Vector3 $pos, World $world) : void{
		foreach($world->getNearbyEntities(
			$target->getBoundingBox()->expandedCopy(self::NEARBY_BEE_ALERT_RADIUS, self::NEARBY_BEE_ALERT_RADIUS, self::NEARBY_BEE_ALERT_RADIUS),
			$target
		) as $entity){
			if($entity instanceof self){
				$entity->setAngry($target);
			}
		}
	}

	private function clearAnger() : void{
		$this->angerTicks = 0;
		$this->angry = false;
		$this->setTargetEntity(null);
		$this->networkPropertiesDirty = true;
	}

	private function performSting(Living $target) : void{
		$ev = new EntityDamageByEntityEvent(
			$this,
			$target,
			EntityDamageEvent::CAUSE_ENTITY_ATTACK,
			self::STING_DAMAGE
		);
		$target->attack($ev);
		if($ev->isCancelled()){
			return;
		}

		$this->broadcastAnimation(new BeeStingAnimation($this));
		$this->broadcastSound(new BeeStingSound());

		$target->getEffects()->add(new EffectInstance(VanillaEffects::POISON(), self::STING_POISON_DURATION, 0, true));

		$this->hasStung = true;
		$this->setHasNectar(false);
		$this->networkPropertiesDirty = true;
		$this->pollinateTicks = 0;
		$this->flowerTarget = null;
		$this->stingDeathTicks = self::STING_DEATH_DELAY;
		$this->clearAnger();
		$this->desiredDirection = $this->generateRandomDirection();
	}

	private function tickCombat(int $tickDiff) : bool{
		if($this->hasStung){
			$this->stingDeathTicks = max(0, $this->stingDeathTicks - $tickDiff);
			if($this->stingDeathTicks === 0){
				$this->kill();
				return true;
			}
			return false;
		}

		if($this->angerTicks <= 0){
			if($this->angry){
				$this->clearAnger();
			}
			return false;
		}

		$this->angerTicks = max(0, $this->angerTicks - $tickDiff);

		$target = $this->getTargetEntity();
		if(!($target instanceof Living) || !$target->isAlive() || $target->getWorld() !== $this->getWorld()){
			$this->clearAnger();
			return true;
		}

		if($target instanceof Player && $target->isCreative()){
			$this->clearAnger();
			return true;
		}

		$this->currentSpeed = self::FLY_SPEED_ANGRY;
		$targetCenter = $target->location->add(0, $target->getSize()->getHeight() * 0.5, 0);
		$distSq = $this->distanceSqTo($targetCenter);
		$this->steerTowards($targetCenter);
		if($distSq <= self::STING_RANGE_SQ){
			$this->performSting($target);
		}

		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled() || !$this->isAlive() || $this->hasStung){
			return;
		}

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager !== null && $damager !== $this && !$damager->closed){
				$this->setAngry($damager);
				self::alertNearbyBees($damager, $this->location, $this->getWorld());
			}
		}
	}

	private function getGroundHeight() : float{
		$world = $this->getWorld();
		$x = (int) $this->location->x;
		$z = (int) $this->location->z;
		$y = (int) $this->location->y;
		for($checkY = $y; $checkY >= max($world->getMinY(), $y - 10); --$checkY){
			$block = $world->getBlockAt($x, $checkY, $z);
			if($block->isSolid()){
				return (float) ($checkY + 1);
			}
		}
		return (float) $world->getMinY();
	}

	private function applyFlight(int $tickDiff) : void{
		$world = $this->getWorld();
		$minY = $world->getMinY() + 2;
		$maxY = $world->getMaxY() - 3;

		if($this->isCollidedHorizontally){
			$this->desiredDirection = new Vector3(
				-$this->desiredDirection->x + (mt_rand(-200, 200) / 1000),
				0.0,
				-$this->desiredDirection->z + (mt_rand(-200, 200) / 1000)
			);
			$len = $this->desiredDirection->lengthSquared();
			if($len > 0.0001){
				$this->desiredDirection = $this->desiredDirection->normalize();
			}
			$this->wanderTicker = 0;
		}

		if($this->homeHive !== null && !$this->angry){
			$dx = $this->location->x - ($this->homeHive->x + 0.5);
			$dz = $this->location->z - ($this->homeHive->z + 0.5);
			$distFromHiveSq = $dx * $dx + $dz * $dz;
			if($distFromHiveSq > self::MAX_WANDER_DISTANCE_SQ){
				$this->steerTowards(new Vector3($this->homeHive->x + 0.5, $this->location->y, $this->homeHive->z + 0.5));
			}
		}

		$this->groundCheckTicker += $tickDiff;
		if($this->groundCheckTicker >= self::GROUND_CHECK_INTERVAL){
			$this->groundCheckTicker = 0;
			$this->cachedGroundY = $this->getGroundHeight();
		}
		$groundY = $this->cachedGroundY;
		$yCorrection = 0.0;
		$isAttacking = $this->angry && !$this->hasStung && $this->getTargetEntity() !== null;
		$hasTask = $this->hasActiveTarget;
		$maxYSpeed = $isAttacking ? self::FLY_MAX_Y_SPEED_ANGRY : self::FLY_MAX_Y_SPEED;
		if($hasTask && !$isAttacking){
			$maxYSpeed = max($maxYSpeed, 0.09);
		}

		$hasPreciseVerticalTask = !$isAttacking && ($this->flowerTarget !== null || $this->homeHive !== null);
		if($hasPreciseVerticalTask){
			$yDiff = $this->activeTargetY - $this->location->y;
			if(abs($yDiff) > 0.05){
				$yCorrection = $yDiff > 0
					? min($yDiff * 0.12, 0.18)
					: max($yDiff * 0.12, -0.18);
			}
		}elseif($hasTask && !$isAttacking){
			$yDiff = $this->activeTargetY - $this->location->y;
			if(abs($yDiff) > 0.2){
				$yCorrection = $yDiff > 0
					? min($yDiff * 0.10, 0.14)
					: max($yDiff * 0.10, -0.14);
			}else{
				$targetY = $groundY + $this->hoverTargetY;
				$yDiff = $targetY - $this->location->y;
				if($this->location->y > $maxY){
					$yCorrection = -0.3;
				}elseif($this->location->y < $minY){
					$yCorrection = 0.3;
				}elseif(abs($yDiff) > 0.25){
					$yCorrection = $yDiff > 0 ? min($yDiff * 0.08, 0.12) : max($yDiff * 0.12, -0.15);
				}
			}
		}elseif($isAttacking){
			$target = $this->getTargetEntity();
			if($target !== null){
				$attackTargetY = $target->location->y + $target->getSize()->getHeight() * 0.5;
				$yDiff = $attackTargetY - $this->location->y;
				if(abs($yDiff) > 0.3){
					$yCorrection = $yDiff > 0 ? min($yDiff * 0.13, 0.22) : max($yDiff * 0.13, -0.22);
				}
			}
		}else{
			$targetY = $groundY + $this->hoverTargetY;
			$yDiff = $targetY - $this->location->y;
			if($this->location->y > $maxY){
				$yCorrection = -0.3;
			}elseif($this->location->y < $minY){
				$yCorrection = 0.3;
			}elseif(abs($yDiff) > 0.25){
				$yCorrection = $yDiff > 0 ? min($yDiff * 0.08, 0.12) : max($yDiff * 0.12, -0.15);
			}
		}

		$this->wanderTicker += $tickDiff;
		if($this->wanderTicker >= self::FLY_WANDER_INTERVAL){
			$this->wanderTicker = 0;
			if($this->flowerTarget === null && !$this->hasNectar && !$this->angry && $this->getTargetEntity() === null){
				$this->desiredDirection = $this->generateRandomDirection();
				$this->hoverTargetY = self::FLY_HOVER_MIN_Y + (mt_rand(0, 3000) / 1000.0) * (self::FLY_HOVER_MAX_Y - self::FLY_HOVER_MIN_Y);
			}
		}

		$hover = (float) (mt_rand(-100, 100) / 10000) * self::FLY_HOVER_Y_VARIANCE;
		$flyX = $this->desiredDirection->x * $this->currentSpeed;
		$flyZ = $this->desiredDirection->z * $this->currentSpeed;
		$dirY = ($isAttacking || $hasTask) ? $this->desiredDirection->y * $this->currentSpeed : 0.0;

		if($this->isPollinating){
			$flyY = 0.0;
			$flyX = 0.0;
			$flyZ = 0.0;
		}else{
			$flyY = $yCorrection + self::FLY_GRAVITY_OFFSET + $hover + $dirY;
		}

		if($isAttacking){
			$this->separationTicker += $tickDiff;
			if($this->separationTicker >= self::SEPARATION_UPDATE_INTERVAL){
				$this->separationTicker = 0;
				$sepX = 0.0;
				$sepZ = 0.0;
				$nearbyCount = 0;
				$sepRadiusSq = self::SEPARATION_RADIUS * self::SEPARATION_RADIUS;
				foreach($world->getNearbyEntities(
					$this->getBoundingBox()->expandedCopy(self::SEPARATION_RADIUS, self::SEPARATION_RADIUS, self::SEPARATION_RADIUS),
					$this
				) as $nearby){
					if(!($nearby instanceof self)){
						continue;
					}
					$ndx = $this->location->x - $nearby->location->x;
					$ndz = $this->location->z - $nearby->location->z;
					$nDistSq = $ndx * $ndx + $ndz * $ndz;
					if($nDistSq < 0.001 || $nDistSq > $sepRadiusSq){
						continue;
					}
					$nDist = sqrt($nDistSq);
					$factor = (1.0 - $nDist / self::SEPARATION_RADIUS) * self::SEPARATION_STRENGTH;
					$sepX += ($ndx / $nDist) * $factor;
					$sepZ += ($ndz / $nDist) * $factor;
					if(++$nearbyCount >= self::SEPARATION_MAX_NEIGHBORS){
						break;
					}
				}
				$this->cachedSepX = $sepX;
				$this->cachedSepZ = $sepZ;
			}
			$flyX += $this->cachedSepX;
			$flyZ += $this->cachedSepZ;
		}else{
			$this->cachedSepX = 0.0;
			$this->cachedSepZ = 0.0;
		}

		if($this->isPollinating){
			// tryPollinate already set motion directly; skip blend/clamp
		}else{
			$this->motion = new Vector3(
				$this->motion->x * 0.2 + $flyX * 0.8,
				$this->motion->y * 0.5 + $flyY * 0.5,
				$this->motion->z * 0.2 + $flyZ * 0.8
			);

			if(abs($this->motion->y) > $maxYSpeed){
				$clampedY = $this->motion->y > 0 ? $maxYSpeed : -$maxYSpeed;
				$this->motion = new Vector3($this->motion->x, $clampedY, $this->motion->z);
			}

			$hSq = ($this->motion->x ** 2) + ($this->motion->z ** 2);
			$maxSpd = $isAttacking
				? (float) self::FLY_SPEED_ANGRY
				: (float) max(self::FLY_MAX_SPEED, $this->currentSpeed + 0.02);
			if($hSq > $maxSpd * $maxSpd){
				$scale = $maxSpd / sqrt($hSq);
				$this->motion = new Vector3(
					$this->motion->x * $scale,
					$this->motion->y,
					$this->motion->z * $scale
				);
			}
		}

		$hSpeed = sqrt(($this->motion->x ** 2) + ($this->motion->z ** 2));
		$yaw = -atan2($this->motion->x, $this->motion->z) * 180.0 / M_PI;
		$pitch = -atan2($hSpeed, $this->motion->y) * 180.0 / M_PI;

		if(!$this->angry && !$this->hasStung && $this->cachedFlowerHolder !== null){
			$lookDx = $this->cachedFlowerHolder->location->x - $this->location->x;
			$lookDz = $this->cachedFlowerHolder->location->z - $this->location->z;
			if(($lookDx * $lookDx + $lookDz * $lookDz) > 0.0001){
				$yaw = -atan2($lookDx, $lookDz) * 180.0 / M_PI;
			}
		}

		$this->setRotation($yaw, $pitch);
	}

	private function findNearbyFlowerHolder() : ?Player{
		$world = $this->getWorld();
		$best = null;
		$bestDist = self::FOLLOW_FLOWER_RANGE * self::FOLLOW_FLOWER_RANGE;

		foreach($world->getPlayers() as $player){
			if(!self::isPlayerHoldingBreedingFlower($player)){
				continue;
			}
			$d = $this->distanceSqTo($player->location);
			if($d < $bestDist){
				$bestDist = $d;
				$best = $player;
			}
		}
		return $best;
	}

	private function tickFollowFlower(int $tickDiff) : bool{
		if($this->angry || $this->hasStung){
			return false;
		}
		$this->followScanCooldown = max(0, $this->followScanCooldown - $tickDiff);
		if(
			$this->cachedFlowerHolder === null ||
			!$this->cachedFlowerHolder->isConnected() ||
			!$this->cachedFlowerHolder->isAlive() ||
			$this->cachedFlowerHolder->isSpectator() ||
			$this->cachedFlowerHolder->getWorld() !== $this->getWorld() ||
			!self::isPlayerHoldingBreedingFlower($this->cachedFlowerHolder) ||
			$this->distanceSqTo($this->cachedFlowerHolder->location) > self::FOLLOW_FLOWER_RANGE * self::FOLLOW_FLOWER_RANGE
		){
			$this->cachedFlowerHolder = null;
		}
		if($this->followScanCooldown === 0){
			$this->followScanCooldown = self::FOLLOW_SCAN_INTERVAL;
			if($this->cachedFlowerHolder === null){
				$this->cachedFlowerHolder = $this->findNearbyFlowerHolder();
			}
		}
		$holder = $this->cachedFlowerHolder;
		if($holder === null){
			return false;
		}

		$holderCenter = $holder->location->add(0, $holder->getSize()->getHeight() * 0.6, 0);
		$dx = $this->location->x - $holderCenter->x;
		$dz = $this->location->z - $holderCenter->z;
		$distSq = $dx * $dx + $dz * $dz;
		if($distSq < 0.0001){
			$dx = ($this->getId() % 2 === 0) ? 1.0 : -1.0;
			$dz = (($this->getId() / 2) % 2 === 0) ? 1.0 : -1.0;
			$distSq = $dx * $dx + $dz * $dz;
		}
		$dist = sqrt($distSq);
		$nx = $dx / $dist;
		$nz = $dz / $dist;
		$followTarget = new Vector3(
			$holderCenter->x + $nx * self::FOLLOW_TARGET_OFFSET,
			$holderCenter->y,
			$holderCenter->z + $nz * self::FOLLOW_TARGET_OFFSET
		);

		if($distSq <= self::FOLLOW_MIN_DISTANCE_SQ){
			$escapeTarget = new Vector3(
				$holderCenter->x + $nx * (self::FOLLOW_MIN_DISTANCE + 0.5),
				$holderCenter->y,
				$holderCenter->z + $nz * (self::FOLLOW_MIN_DISTANCE + 0.5)
			);
			$this->steerTowards($escapeTarget);
			$this->currentSpeed = self::FOLLOW_SPEED * 0.75;
			return true;
		}

		if($distSq <= self::FOLLOW_HOLD_DISTANCE_SQ){
			$this->hasActiveTarget = true;
			$this->activeTargetY = $holderCenter->y;
			$this->currentSpeed = 0.0;
			$this->motion = new Vector3(
				$this->motion->x * 0.6,
				$this->motion->y,
				$this->motion->z * 0.6
			);
			return true;
		}

		$this->steerTowards($followTarget);
		$this->currentSpeed = self::FOLLOW_SPEED;
		return true;
	}

	private function tickBreeding(int $tickDiff) : void{
		if($this->baby || !$this->inLove){
			return;
		}
		$this->loveTicks = max(0, $this->loveTicks - $tickDiff);
		if($this->loveTicks <= 0){
			$this->inLove = false;
			$this->networkPropertiesDirty = true;
			return;
		}

		$this->heartParticleTicker += $tickDiff;
		if($this->heartParticleTicker >= self::HEART_PARTICLE_INTERVAL){
			$this->heartParticleTicker = 0;
			$this->getWorld()->addParticle(
				$this->location->add(
					(mt_rand(-30, 30) / 100.0),
					$this->getSize()->getHeight() + 0.2,
					(mt_rand(-30, 30) / 100.0)
				),
				new HeartParticle()
			);
		}

		$world = $this->getWorld();
		$this->breedScanCooldown = max(0, $this->breedScanCooldown - $tickDiff);
		if(
			$this->cachedBreedMate !== null && (
				!$this->cachedBreedMate->isAlive() ||
				$this->cachedBreedMate->closed ||
				$this->cachedBreedMate->baby ||
				!$this->cachedBreedMate->inLove ||
				$this->cachedBreedMate->getWorld() !== $world
			)
		){
			$this->cachedBreedMate = null;
		}

		if($this->breedScanCooldown === 0){
			$this->breedScanCooldown = self::BREED_SCAN_INTERVAL;
			$nearest = null;
			$nearestDist = 64.0;
			foreach($world->getNearbyEntities(
				$this->getBoundingBox()->expandedCopy(8, 4, 8),
				$this
			) as $entity){
				if(!($entity instanceof self) || $entity->baby || !$entity->inLove || $entity->getId() <= $this->getId()){
					continue;
				}
				$d = $this->distanceSqTo($entity->location);
				if($d < $nearestDist){
					$nearestDist = $d;
					$nearest = $entity;
				}
			}
			$this->cachedBreedMate = $nearest;
		}

		$nearest = $this->cachedBreedMate;
		$nearestDist = $nearest !== null ? $this->distanceSqTo($nearest->location) : 64.0;

		if($nearest === null){
			return;
		}

		$this->steerTowards($nearest->location);
		$nearest->steerTowards($this->location);
		$this->currentSpeed = self::FOLLOW_SPEED;
		$nearest->currentSpeed = self::FOLLOW_SPEED;

		if($nearestDist > self::BREED_RANGE_SQ){
			return;
		}

		$midX = ($this->location->x + $nearest->location->x) / 2;
		$midY = ($this->location->y + $nearest->location->y) / 2;
		$midZ = ($this->location->z + $nearest->location->z) / 2;

		$babyBee = new self(Location::fromObject(new Vector3($midX, $midY + 0.5, $midZ), $world, mt_rand(0, 360), 0));
		$babyBee->setBaby();
		$babyBee->spawnToAll();

		$world->dropExperience(new Vector3($midX, $midY, $midZ), mt_rand(self::BREED_XP_MIN, self::BREED_XP_MAX));

		for($i = 0; $i < 7; ++$i){
			$world->addParticle(
				new Vector3(
					$midX + (mt_rand(-50, 50) / 100.0),
					$midY + 0.5 + (mt_rand(0, 50) / 100.0),
					$midZ + (mt_rand(-50, 50) / 100.0)
				),
				new HeartParticle()
			);
		}

		$this->inLove = false;
		$this->loveTicks = 0;
		$this->loveCooldown = self::BREED_COOLDOWN;
		$this->heartParticleTicker = 0;
		$this->cachedBreedMate = null;
		$this->networkPropertiesDirty = true;

		$nearest->inLove = false;
		$nearest->loveTicks = 0;
		$nearest->loveCooldown = self::BREED_COOLDOWN;
		$nearest->heartParticleTicker = 0;
		$nearest->cachedBreedMate = null;
		$nearest->networkPropertiesDirty = true;
	}

	private function tickBabyGrowth(int $tickDiff) : void{
		if(!$this->baby){
			return;
		}
		$this->ageTicks += $tickDiff;
		if($this->ageTicks >= self::BABY_GROW_TICKS){
			$this->setBaby(false);
			$this->ageTicks = 0;
		}
	}

	private function tickBabyFollow(int $tickDiff) : bool{
		if(!$this->baby || $this->angry || $this->hasStung){
			return false;
		}
		$world = $this->getWorld();
		$this->babyFollowScanCooldown = max(0, $this->babyFollowScanCooldown - $tickDiff);
		if(
			$this->cachedBabyLeader !== null && (
				!$this->cachedBabyLeader->isAlive() ||
				$this->cachedBabyLeader->closed ||
				$this->cachedBabyLeader->baby ||
				$this->cachedBabyLeader->getWorld() !== $world
			)
		){
			$this->cachedBabyLeader = null;
		}

		if($this->babyFollowScanCooldown === 0){
			$this->babyFollowScanCooldown = self::BABY_FOLLOW_SCAN_INTERVAL;
			$nearest = null;
			$nearestDist = 100.0;
			foreach($world->getNearbyEntities(
				$this->getBoundingBox()->expandedCopy(10, 6, 10),
				$this
			) as $entity){
				if(!($entity instanceof self) || $entity->baby || !$entity->isAlive()){
					continue;
				}
				$d = $this->distanceSqTo($entity->location);
				if($d < $nearestDist){
					$nearestDist = $d;
					$nearest = $entity;
				}
			}
			$this->cachedBabyLeader = $nearest;
		}

		$nearest = $this->cachedBabyLeader;
		$nearestDist = $nearest !== null ? $this->distanceSqTo($nearest->location) : 100.0;

		if($nearest === null){
			return false;
		}

		if($nearestDist > 4.0){
			$this->steerTowards($nearest->location->add(0, 0.3, 0));
			$this->currentSpeed = self::FOLLOW_SPEED;
		}
		return true;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->closed){
			return false;
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->isAlive()){
			if($this->loveCooldown > 0){
				$this->loveCooldown = max(0, $this->loveCooldown - $tickDiff);
			}

			$this->hasActiveTarget = false;
			$this->isPollinating = false;

			$this->tickBabyGrowth($tickDiff);
			$this->tickBreeding($tickDiff);

			$following = $this->tickFollowFlower($tickDiff);
			if(!$following && $this->baby){
				$following = $this->tickBabyFollow($tickDiff);
			}

			if(!$this->tickCombat($tickDiff) && !$this->hasStung && !$following){
				$this->tickGathering($tickDiff);
			}
			$this->applyFlight($tickDiff);
		}

		return $hasUpdate;
	}

	public function getDrops() : array{
		return [];
	}

	public function getXpDropAmount() : int{
		return $this->baby ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::BEE_SPAWN_EGG();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->angry);
		$properties->setGenericFlag(EntityMetadataFlags::INLOVE, $this->inLove);
		$properties->setInt(EntityMetadataProperties::MARK_VARIANT, $this->hasStung ? 1 : 0);
	}

	public function getPropertySyncData() : PropertySyncData{
		return new PropertySyncData([0 => $this->hasNectar ? 1 : 0], []);
	}

	protected function onDispose() : void{
		if($this->flowerTarget !== null){
			self::releaseFlower((int) $this->flowerTarget->x, (int) $this->flowerTarget->y, (int) $this->flowerTarget->z, $this->getId());
		}
		parent::onDispose();
	}
}