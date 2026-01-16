<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\sound\MaceHeavySmashGroundSound;
use pocketmine\world\sound\MaceSmashAirSound;
use pocketmine\world\sound\MaceSmashGroundSound;
use pocketmine\world\World;

class Mace extends Tool{

	private const SMASH_TRIGGER_FALL_DISTANCE = 1.5;
	private const SMASH_BLOCKS_HIGH = 3;
	private const SMASH_BLOCKS_MID = 5;
	private const SMASH_DAMAGE_HIGH = 3.0;
	private const SMASH_DAMAGE_MID = 1.5;
	private const SMASH_DAMAGE_LOW = 1.0;
	private const HEAVY_SMASH_DAMAGE = 16.0;
	private const SMASH_RECOIL_Y = 0.05;
	private const SMASH_KNOCKBACK_RADIUS = 3.0;
	private const SMASH_KNOCKBACK_VERTICAL_RANGE = 2.0;
	private const SMASH_AOE_KNOCKBACK_STRENGTH = 0.35;
	private const SMASH_AOE_KNOCKBACK_Y = 0.60;
	private const SMASH_VICTIM_KNOCKBACK_STRENGTH = 0.35;
	private const SMASH_VICTIM_KNOCKBACK_Y = 0.60;

	public function getMaxDurability() : int{
		return 501;
	}

	public function getAttackPoints() : int{
		return 5; 
	}

	/**
	 * Gets the smash bonus damage for the attacker
	 * This should be called from Player::attackEntity to modify the damage event
	 */
	public function getSmashBonusDamage(Entity $attacker) : float{
		if($this->isSmashAttack($attacker)){
			return $this->calculateSmashBonus($attacker);
		}
		return 0.0;
	}

	/**
	 * Applies smash effects after damage is dealt
	 * This should be called from Player::attackEntity after the attack
	 */
	public function applySmashEffectsIfNeeded(Entity $attacker, Entity $victim, float $totalDamage) : void{
		if($this->isSmashAttack($attacker)){
			$this->applySmashEffects($attacker, $victim, $totalDamage);
		}
	}

	public function onAttackEntity(Entity $victim, array &$returnedItems) : bool{
		return $this->applyDamage(1);
	}

	/**
	 * Checks if the attack qualifies as a smash attack
	 */
	private function isSmashAttack(Entity $attacker) : bool{
		return $attacker->getFallDistance() > self::SMASH_TRIGGER_FALL_DISTANCE;
	}

	/**
	 * Calculates bonus damage based on fall distance
	 */
	private function calculateSmashBonus(Entity $attacker) : float{
		$fallDistance = $attacker->getFallDistance();
		if($fallDistance <= self::SMASH_TRIGGER_FALL_DISTANCE){
			return 0.0;
		}

		$fallBlocks = (int) floor($fallDistance);
		$bonus = 0.0;
		
		for($i = 1; $i <= $fallBlocks; $i++){
			if($i <= self::SMASH_BLOCKS_HIGH){
				$bonus += self::SMASH_DAMAGE_HIGH;
			}elseif($i <= self::SMASH_BLOCKS_HIGH + self::SMASH_BLOCKS_MID){
				$bonus += self::SMASH_DAMAGE_MID;
			}else{
				$bonus += self::SMASH_DAMAGE_LOW;
			}
		}

		return $bonus;
	}

	/**
	 * Applies all smash attack effects
	 */
	private function applySmashEffects(Entity $attacker, Entity $victim, float $totalDamage) : void{
		$world = $attacker->getWorld();
		$effectLocation = $victim->getLocation();

		$attacker->resetFallDistance();

		$motion = $attacker->getMotion();
		$attacker->setMotion(new Vector3($motion->x, self::SMASH_RECOIL_Y, $motion->z));

		if($attacker->isOnGround()){
			if($totalDamage >= self::HEAVY_SMASH_DAMAGE){
				$world->addSound($effectLocation, new MaceHeavySmashGroundSound());
			}else{
				$world->addSound($effectLocation, new MaceSmashGroundSound());
			}
		}else{
			$world->addSound($effectLocation, new MaceSmashAirSound());
		}

		$this->spawnSmashParticles($world, $victim);
		$this->applySmashVictimKick($attacker, $victim);
		$this->applySmashKnockback($world, $attacker, $victim);
	}

	/**
	 * Spawns ground dust particles at impact location
	 */
	private function spawnSmashParticles(World $world, Entity $victim) : void{
		$center = $victim->getLocation();
		$aabb = $victim->getBoundingBox();
		$blockX = (int) floor($center->x);
		$blockZ = (int) floor($center->z);
		$blockY = (int) floor($aabb->minY - 0.01);
		
		$blockUnder = $world->getBlockAt($blockX, $blockY, $blockZ);
		if($blockUnder->getTypeId() === VanillaBlocks::AIR()->getTypeId()){
			return;
		}

		$particlePos = new Vector3($center->x, $blockY + 1.0, $center->z);
		$world->addParticle($particlePos, new BlockBreakParticle($blockUnder));
	}

	/**
	 * Applies knockback to the direct victim
	 */
	private function applySmashVictimKick(Entity $attacker, Entity $victim) : void{
		if($victim instanceof Living){
			$dx = $victim->getLocation()->x - $attacker->getLocation()->x;
			$dz = $victim->getLocation()->z - $attacker->getLocation()->z;
			$victim->knockBack($dx, $dz, self::SMASH_VICTIM_KNOCKBACK_STRENGTH, self::SMASH_VICTIM_KNOCKBACK_Y);
		}
	}

	/**
	 * Applies area-of-effect knockback to nearby entities
	 */
	private function applySmashKnockback(World $world, Entity $attacker, Entity $victim) : void{
		$centerLocation = $victim->getLocation();
		$centerX = $centerLocation->x;
		$centerY = $centerLocation->y;
		$centerZ = $centerLocation->z;
		
		$aabb = $victim->getBoundingBox()->expandedCopy(self::SMASH_KNOCKBACK_RADIUS, self::SMASH_KNOCKBACK_RADIUS, self::SMASH_KNOCKBACK_RADIUS);
		$horizontalRadiusSquared = self::SMASH_KNOCKBACK_RADIUS * self::SMASH_KNOCKBACK_RADIUS;

		foreach($world->getNearbyEntities($aabb, $attacker) as $entity){
			if($entity === $victim || !($entity instanceof Living)){
				continue;
			}

			$entityLocation = $entity->getLocation();
			
			if(abs($entityLocation->y - $centerY) > self::SMASH_KNOCKBACK_VERTICAL_RANGE){
				continue;
			}

			$dx = $entityLocation->x - $centerX;
			$dz = $entityLocation->z - $centerZ;
			if(($dx * $dx + $dz * $dz) > $horizontalRadiusSquared){
				continue;
			}

			$entity->knockBack($dx, $dz, self::SMASH_AOE_KNOCKBACK_STRENGTH, self::SMASH_AOE_KNOCKBACK_Y);
		}
	}
}
