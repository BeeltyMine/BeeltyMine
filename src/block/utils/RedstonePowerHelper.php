<?php

declare(strict_types=1);

namespace pocketmine\block\utils;

use pocketmine\block\Block;
use pocketmine\block\Button;
use pocketmine\block\DaylightSensor;
use pocketmine\block\DetectorRail;
use pocketmine\block\Lever;
use pocketmine\block\Observer;
use pocketmine\block\Redstone;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\block\SimplePressurePlate;
use pocketmine\block\TripwireHook;
use pocketmine\block\WeightedPressurePlate;
use pocketmine\math\Facing;
use function max;

final class RedstonePowerHelper{
	private function __construct(){
		//NOOP
	}

	public static function getStrongestNeighborPower(Block $block, bool $ignoreWire = false) : int{
		$maxPower = 0;
		foreach(Facing::OFFSET as $face => $_){
			$maxPower = max($maxPower, self::getPowerFromFace($block, $face, $ignoreWire));
			if($maxPower >= 15){
				return 15;
			}
		}

		return $maxPower;
	}

	public static function getPowerFromFace(Block $block, int $face, bool $ignoreWire = false) : int{
		$source = $block->getSide($face);
		if($ignoreWire && $source instanceof RedstoneWire){
			return 0;
		}

		$towards = Facing::opposite($face);
		if(self::isNormalBlock($source)){
			return self::getStrongPowerAt($source, $ignoreWire);
		}

		return self::getWeakPowerTowards($source, $towards);
	}

	private static function getStrongPowerAt(Block $solidBlock, bool $ignoreWire) : int{
		$maxPower = 0;
		foreach(Facing::OFFSET as $face => $_){
			$source = $solidBlock->getSide($face);
			if($ignoreWire && $source instanceof RedstoneWire){
				continue;
			}

			$maxPower = max($maxPower, self::getStrongPowerTowards($source, Facing::opposite($face)));
			if($maxPower >= 15){
				return 15;
			}
		}

		return $maxPower;
	}

	public static function getEmittedPowerTowards(Block $source, int $towards) : int{
		return self::getWeakPowerTowards($source, $towards);
	}

	private static function isNormalBlock(Block $block) : bool{
		return !$block->isTransparent() && $block->isSolid() && !self::isPowerSource($block);
	}

	private static function isPowerSource(Block $block) : bool{
		return $block instanceof Redstone ||
			$block instanceof Lever ||
			$block instanceof Button ||
			$block instanceof SimplePressurePlate ||
			$block instanceof WeightedPressurePlate ||
			$block instanceof DaylightSensor ||
			$block instanceof RedstoneWire ||
			$block instanceof DetectorRail ||
			$block instanceof TripwireHook ||
			$block instanceof RedstoneTorch ||
			$block instanceof RedstoneRepeater ||
			$block instanceof RedstoneComparator ||
			$block instanceof Observer;
	}

	private static function getWeakPowerTowards(Block $source, int $towards) : int{
		if($source instanceof Redstone){
			return 15;
		}

		if($source instanceof Lever){
			return $source->isActivated() ? 15 : 0;
		}

		if($source instanceof Button){
			return $source->isPressed() ? 15 : 0;
		}

		if($source instanceof SimplePressurePlate){
			return $source->isPressed() ? 15 : 0;
		}

		if($source instanceof WeightedPressurePlate){
			return $source->getOutputSignalStrength();
		}

		if($source instanceof DaylightSensor){
			return $source->getOutputSignalStrength();
		}

		if($source instanceof RedstoneWire){
			return $source->getOutputSignalStrength();
		}

		if($source instanceof DetectorRail){
			return $source->isActivated() ? 15 : 0;
		}

		if($source instanceof TripwireHook){
			return $source->isPowered() ? 15 : 0;
		}

		if($source instanceof RedstoneTorch){
			if(!$source->isLit()){
				return 0;
			}

			return $towards === Facing::opposite($source->getFacing()) ? 0 : 15;
		}

		if($source instanceof RedstoneRepeater){
			return $source->isPowered() && $towards === Facing::opposite($source->getFacing()) ? 15 : 0;
		}

		if($source instanceof RedstoneComparator){
			return $source->isPowered() && $towards === Facing::opposite($source->getFacing()) ? $source->getOutputSignalStrength() : 0;
		}

		if($source instanceof Observer){
			return $source->isPowered() && $towards === Facing::opposite($source->getFacing()) ? 15 : 0;
		}

		return 0;
	}

	private static function getStrongPowerTowards(Block $source, int $towards) : int{
		if($source instanceof Lever){
			return $source->isActivated() && $towards === $source->getFacing()->getFacing() ? 15 : 0;
		}

		if($source instanceof Button){
			return $source->isPressed() && $towards === $source->getFacing() ? 15 : 0;
		}

		if($source instanceof SimplePressurePlate){
			return $source->isPressed() && $towards === Facing::UP ? 15 : 0;
		}

		if($source instanceof WeightedPressurePlate){
			return $towards === Facing::UP ? $source->getOutputSignalStrength() : 0;
		}

		if($source instanceof DetectorRail){
			return $source->isActivated() && $towards === Facing::UP ? 15 : 0;
		}

		if($source instanceof TripwireHook){
			return $source->isPowered() && $towards === $source->getFacing() ? 15 : 0;
		}

		if($source instanceof RedstoneTorch){
			return $towards === Facing::DOWN ? self::getWeakPowerTowards($source, $towards) : 0;
		}

		if($source instanceof RedstoneRepeater){
			return $source->isPowered() && $towards === Facing::opposite($source->getFacing()) ? 15 : 0;
		}

		if($source instanceof RedstoneComparator){
			return $source->isPowered() && $towards === Facing::opposite($source->getFacing()) ? $source->getOutputSignalStrength() : 0;
		}

		if($source instanceof Observer){
			return $source->isPowered() && $towards === Facing::opposite($source->getFacing()) ? 15 : 0;
		}

		return 0;
	}
}