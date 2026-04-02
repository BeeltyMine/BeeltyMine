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

namespace pocketmine\data\runtime;

use pocketmine\block\utils\BellAttachmentType;
use pocketmine\block\utils\CopperOxidation;
use pocketmine\block\utils\CoralType;
use pocketmine\block\utils\DirtType;
use pocketmine\block\utils\DripleafState;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\FroglightType;
use pocketmine\block\utils\LeverFacing;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\utils\MushroomBlockType;
use pocketmine\block\utils\SlabType;
use pocketmine\item\MedicineType as ItemMedicineType;
use pocketmine\item\PotionType;
use pocketmine\item\SuspiciousStewType;

/**
 * Provides backwards-compatible shims for the old codegen'd enum describer methods.
 * This is kept for plugin backwards compatibility, but these functions should not be used in new code.
 * @deprecated
 */
trait LegacyRuntimeEnumDescriberTrait{
	abstract protected function enum(\UnitEnum &$case) : void;

	public function bellAttachmentType(BellAttachmentType &$value) : void{
		$this->enum($value);
	}

	public function copperOxidation(CopperOxidation &$value) : void{
		$this->enum($value);
	}

	public function coralType(CoralType &$value) : void{
		$this->enum($value);
	}

	public function dirtType(DirtType &$value) : void{
		$this->enum($value);
	}

	public function dripleafState(DripleafState &$value) : void{
		$this->enum($value);
	}

	public function dyeColor(DyeColor &$value) : void{
		$this->enum($value);
	}

	public function froglightType(FroglightType &$value) : void{
		$this->enum($value);
	}

	public function leverFacing(LeverFacing &$value) : void{
		$this->enum($value);
	}

	public function medicineType(ItemMedicineType &$value) : void{
		$this->enum($value);
	}

	public function mobHeadType(MobHeadType &$value) : void{
		$this->enum($value);
	}

	public function mushroomBlockType(MushroomBlockType &$value) : void{
		$this->enum($value);
	}

	public function potionType(PotionType &$value) : void{
		$this->enum($value);
	}

	public function slabType(SlabType &$value) : void{
		$this->enum($value);
	}

	public function suspiciousStewType(SuspiciousStewType &$value) : void{
		$this->enum($value);
	}
}
