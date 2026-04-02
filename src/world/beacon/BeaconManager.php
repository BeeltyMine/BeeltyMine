<?php

declare(strict_types=1);

namespace pocketmine\world\beacon;

use pocketmine\block\Beacon as BeaconBlock;
use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\data\bedrock\EffectIdMap;
use pocketmine\data\bedrock\EffectIds;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\World;
use function count;
use function spl_object_id;

final class BeaconManager{
	private const UPDATE_INTERVAL_TICKS = Server::TARGET_TICKS_PER_SECOND * 10;
	private const EFFECT_DURATION_TICKS = self::UPDATE_INTERVAL_TICKS + 5;
	private const EFFECT_REAPPLY_THRESHOLD_TICKS = self::UPDATE_INTERVAL_TICKS;

	/**
	 * @var BeaconTile[][]
	 * @phpstan-var array<int, array<int, BeaconTile>>
	 */
	private array $beaconsByWorld = [];

	/**
	 * @var array<int, array<int, array{player: Player, effect: Effect, amplifier: int}>>
	 */
	private array $appliedEffects = [];

	private bool $dirty = false;

	public function invalidate() : void{
		$this->dirty = true;
	}

	public function registerBeacon(BeaconTile $beacon) : void{
		$world = $beacon->getPosition()->getWorld();
		$this->beaconsByWorld[$world->getId()][spl_object_id($beacon)] = $beacon;
		$this->invalidate();
	}

	public function unregisterBeacon(BeaconTile $beacon) : void{
		$world = $beacon->getPosition()->getWorld();
		unset($this->beaconsByWorld[$world->getId()][spl_object_id($beacon)]);
		if(isset($this->beaconsByWorld[$world->getId()]) && count($this->beaconsByWorld[$world->getId()]) === 0){
			unset($this->beaconsByWorld[$world->getId()]);
		}
		$this->invalidate();
	}

	public function tick(int $currentTick) : void{
		if(!$this->dirty && ($currentTick % self::UPDATE_INTERVAL_TICKS) !== 0){
			return;
		}
		$this->dirty = false;

		$desiredEffects = [];

		foreach($this->beaconsByWorld as $worldId => $beacons){
			foreach($beacons as $beaconId => $beacon){
				if($beacon->isClosed()){
					unset($this->beaconsByWorld[$worldId][$beaconId]);
					continue;
				}

				$world = $beacon->getPosition()->getWorld();
				$block = $beacon->getBlock();
				if(!$block instanceof BeaconBlock){
					unset($this->beaconsByWorld[$worldId][$beaconId]);
					continue;
				}

				$this->collectBeaconEffects($world, $beacon, $desiredEffects);
			}

			if(isset($this->beaconsByWorld[$worldId]) && count($this->beaconsByWorld[$worldId]) === 0){
				unset($this->beaconsByWorld[$worldId]);
			}
		}

		$this->synchronizeEffects($desiredEffects);
	}

	/**
	 * @param array<int, array<int, array{player: Player, effect: Effect, amplifier: int}>> $desiredEffects
	 */
	private function collectBeaconEffects(World $world, BeaconTile $beacon, array &$desiredEffects) : void{
		$pos = $beacon->getPosition();
		$level = BeaconStructure::calculateLevel(function(int $layer, int $offsetX, int $offsetZ) use ($world, $pos) : bool{
			return BeaconStructure::isValidBaseBlockTypeId($world->getBlockAt($pos->getFloorX() + $offsetX, $pos->getFloorY() - $layer, $pos->getFloorZ() + $offsetZ)->getTypeId());
		});
		if($level <= 0){
			$beacon->clearEffects();
			return;
		}

		$primaryEffect = $this->resolvePrimaryEffect($beacon, $level);
		if($primaryEffect === null){
			$beacon->clearEffects();
			return;
		}

		$secondaryEffect = $this->resolveSecondaryEffect($beacon, $primaryEffect, $level);
		if($beacon->getSecondaryEffect() !== 0 && $secondaryEffect === null){
			$beacon->clearSecondaryEffect();
		}
		$primaryAmplifier = BeaconStructure::getPrimaryAmplifier($level, $secondaryEffect !== null && $secondaryEffect === $primaryEffect);
		$range = BeaconStructure::getRangeForLevel($level);

		$bb = new AxisAlignedBB(
			$pos->getFloorX() - $range,
			$world->getMinY(),
			$pos->getFloorZ() - $range,
			$pos->getFloorX() + $range + 1,
			$world->getMaxY(),
			$pos->getFloorZ() + $range + 1
		);

		$rangeSquared = $range * $range;
		foreach($world->getNearbyEntities($bb) as $entity){
			if(!$entity instanceof Player || $entity->isClosed()){
				continue;
			}

			$dx = $entity->getPosition()->x - $pos->x;
			$dz = $entity->getPosition()->z - $pos->z;
			if((($dx * $dx) + ($dz * $dz)) > $rangeSquared){
				continue;
			}

			$this->setDesiredEffect($desiredEffects, $entity, $primaryEffect, $primaryAmplifier);
			if($secondaryEffect !== null){
				$this->setDesiredEffect($desiredEffects, $entity, $secondaryEffect, 0);
			}
		}
	}

	/**
	 * @param array<int, array<int, array{player: Player, effect: Effect, amplifier: int}>> $desiredEffects
	 */
	private function setDesiredEffect(array &$desiredEffects, Player $player, Effect $effect, int $amplifier) : void{
		$playerId = spl_object_id($player);
		$effectId = spl_object_id($effect);
		if(isset($desiredEffects[$playerId][$effectId]) && $desiredEffects[$playerId][$effectId]['amplifier'] >= $amplifier){
			return;
		}

		$desiredEffects[$playerId][$effectId] = [
			'player' => $player,
			'effect' => $effect,
			'amplifier' => $amplifier
		];
	}

	/**
	 * @param array<int, array<int, array{player: Player, effect: Effect, amplifier: int}>> $desiredEffects
	 */
	private function synchronizeEffects(array $desiredEffects) : void{
		foreach($this->appliedEffects as $playerId => $effects){
			foreach($effects as $effectId => $applied){
				if(isset($desiredEffects[$playerId][$effectId])){
					continue;
				}

				$player = $applied['player'];
				if($player->isClosed()){
					continue;
				}

				$current = $player->getEffects()->get($applied['effect']);
				if($current !== null && $current->isAmbient() && $current->getAmplifier() === $applied['amplifier']){
					$player->getEffects()->remove($applied['effect']);
				}
			}
		}

		$nextApplied = [];
		foreach($desiredEffects as $playerId => $effects){
			foreach($effects as $effectId => $desired){
				$player = $desired['player'];
				if($player->isClosed()){
					continue;
				}

				$current = $player->getEffects()->get($desired['effect']);
				if($current !== null && !$current->isAmbient()){
					continue;
				}

				if(
					$current === null ||
					!$current->isAmbient() ||
					$current->getAmplifier() !== $desired['amplifier'] ||
					$current->getDuration() <= self::EFFECT_REAPPLY_THRESHOLD_TICKS
				){
					$player->getEffects()->add(new EffectInstance(
						$desired['effect'],
						self::EFFECT_DURATION_TICKS,
						$desired['amplifier'],
						true,
						true
					));
				}

				$nextApplied[$playerId][$effectId] = $desired;
			}
		}

		$this->appliedEffects = $nextApplied;
	}

	private function resolvePrimaryEffect(BeaconTile $beacon, int $level) : ?Effect{
		$primaryId = $beacon->getPrimaryEffect();
		if($primaryId === 0 || !BeaconEffects::isPrimaryEffectAllowed($level, $primaryId)){
			return null;
		}

		return EffectIdMap::getInstance()->fromId($primaryId);
	}

	private function resolveSecondaryEffect(BeaconTile $beacon, Effect $primaryEffect, int $level) : ?Effect{
		$primaryId = $beacon->getPrimaryEffect();
		$secondaryId = $beacon->getSecondaryEffect();

		if($level < 4 || !BeaconEffects::isSecondaryEffectAllowed($level, $primaryId, $secondaryId)){
			return null;
		}

		if($secondaryId === 0){
			return null;
		}

		if($secondaryId === $primaryId){
			return $primaryEffect;
		}

		return EffectIdMap::getInstance()->fromId($secondaryId === EffectIds::REGENERATION ? EffectIds::REGENERATION : $secondaryId);
	}
}
