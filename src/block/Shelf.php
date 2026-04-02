<?php


/*
 *
 * $$$$$$$\                      $$\   $$\               $$\      $$\ $$\                     
 * $$  __$$\                     $$ |  $$ |              $$$\    $$$ |\__|                    
 * $$ |  $$ | $$$$$$\   $$$$$$\  $$ |$$$$$$\   $$\   $$\ $$$$\  $$$$ |$$\ $$$$$$$\   $$$$$$\  
 * $$$$$$$\ |$$  __$$\ $$  __$$\ $$ |\_$$  _|  $$ |  $$ |$$\$$\$$ $$ |$$ |$$  __$$\ $$  __$$\ 
 * $$  __$$\ $$$$$$$$ |$$$$$$$$ |$$ |  $$ |    $$ |  $$ |$$ \$$$  $$ |$$ |$$ |  $$ |$$$$$$$$ |
 * $$ |  $$ |$$   ____|$$   ____|$$ |  $$ |$$\ $$ |  $$ |$$ |\$  /$$ |$$ |$$ |  $$ |$$   ____|
 * $$$$$$$  |\$$$$$$$\ \$$$$$$$\ $$ |  \$$$$  |\$$$$$$$ |$$ | \_/ $$ |$$ |$$ |  $$ |\$$$$$$$\ 
 * \_______/  \_______| \_______|\__|   \____/  \____$$ |\__|     \__|\__|\__|  \__| \_______|
 *                                            $$\   $$ |                                     
 *                                            \$$$$$$  |                                     
 *                                             \______/                                      
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Ayrz
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\tile\Shelf as TileShelf;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\PoweredShelfType;
use pocketmine\block\utils\WoodMaterial;
use pocketmine\block\utils\WoodType;
use pocketmine\block\utils\WoodTypeTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function array_reverse;

class Shelf extends Transparent implements HorizontalFacing, PoweredByRedstone, WoodMaterial{
	use HorizontalFacingTrait;
	use PoweredByRedstoneTrait;
	use WoodTypeTrait;

	private int $shelfType = PoweredShelfType::UNCONNECTED->value;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->bool($this->powered);
		$w->boundedIntAuto(PoweredShelfType::UNCONNECTED->value, PoweredShelfType::LEFT->value, $this->shelfType);
	}

	public function isSolid() : bool{
		return false;
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim($this->facing, 13 / 16)];
	}

	public function getFlameEncouragement() : int{
		return $this->woodType->isFlammable() ? 30 : 0;
	}

	public function getFlammability() : int{
		return $this->woodType->isFlammable() ? 20 : 0;
	}

	public function getFuelTime() : int{
		return $this->woodType->isFlammable() ? 300 : 0;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = Facing::opposite($player->getHorizontalFacing());
		}
		if(!parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player)){
			return false;
		}

		$this->updateShelfConnection();
		return true;
	}

	public function onNearbyBlockChange() : void{
		parent::onNearbyBlockChange();
		$this->updateShelfConnection();
	}

	public function getShelfType() : PoweredShelfType{
		return PoweredShelfType::from($this->shelfType);
	}

	/** @return $this */
	public function setShelfType(PoweredShelfType $type) : self{
		$this->shelfType = $type->value;
		return $this;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(
			$player === null ||
			$player->isSneaking() ||
			$face !== $this->facing ||
			$clickVector->y <= 0.25 ||
			$clickVector->y >= 0.75
		){
			return false;
		}

		if($this->powered){
			$this->swapConnectedShelves($player);
			return true;
		}

		$tile = $this->position->getWorld()->getTile($this->position);
		if(!$tile instanceof TileShelf){
			return false;
		}

		$slot = $this->resolveInteractedSlot($face, $clickVector);
		$inventory = $tile->getInventory();
		$shelfItem = $inventory->getItem($slot);

		if(!$player->isCreative()){
			$player->getInventory()->setItemInHand($shelfItem);
		}

		$inventory->setItem($slot, $item);
		return true;
	}

	private function resolveInteractedSlot(int $face, Vector3 $clickVector) : int{
		[$offsetX, , $offsetZ] = Facing::OFFSET[Facing::rotateY($face, false)];
		$distance = ($clickVector->x * $offsetX) + ($clickVector->z * $offsetZ);
		if($distance < 0){
			$distance += 1;
		}

		if($distance < (1 / 3)){
			return 0;
		}
		if($distance < (2 / 3)){
			return 1;
		}
		return 2;
	}

	private function swapConnectedShelves(Player $player) : void{
		$playerInventory = $player->getInventory();
		foreach($this->getConnectedShelves() as $shelfIndex => $shelf){
			$tile = $shelf->position->getWorld()->getTile($shelf->position);
			if(!$tile instanceof TileShelf){
				continue;
			}

			$inventory = $tile->getInventory();
			for($slot = 0, $size = $inventory->getSize(); $slot < $size; ++$slot){
				$playerSlot = ($shelfIndex * $size) + $slot;
				if(!$playerInventory->slotExists($playerSlot)){
					return;
				}

				$shelfItem = $inventory->getItem($slot);
				$playerItem = $playerInventory->getItem($playerSlot);
				$inventory->setItem($slot, $playerItem);
				$playerInventory->setItem($playerSlot, $shelfItem);
			}
		}
	}

	/**
	 * @return list<Shelf>
	 */
	private function getConnectedShelves() : array{
		if(!$this->powered || $this->getShelfType() === PoweredShelfType::UNCONNECTED){
			return [$this];
		}

		$clockwise = Facing::rotateY($this->facing, true);
		$right = $this->getSide($clockwise);
		$left = $this->getSide(Facing::opposite($clockwise));
		$result = [];

		switch($this->getShelfType()){
			case PoweredShelfType::CENTER:
				if($right instanceof self){
					$result[] = $right;
				}
				$result[] = $this;
				if($left instanceof self){
					$result[] = $left;
				}
				break;
			case PoweredShelfType::RIGHT:
				$result[] = $this;
				if($right instanceof self){
					$result[] = $right;
					if($right->getShelfType() === PoweredShelfType::CENTER){
						$farRight = $this->getSide($clockwise, 2);
						if($farRight instanceof self){
							$result[] = $farRight;
						}
					}
				}
				$result = array_reverse($result);
				break;
			case PoweredShelfType::LEFT:
				$result[] = $this;
				if($left instanceof self){
					$result[] = $left;
					if($left->getShelfType() === PoweredShelfType::CENTER){
						$farLeft = $this->getSide(Facing::opposite($clockwise), 2);
						if($farLeft instanceof self){
							$result[] = $farLeft;
						}
					}
				}
				break;
			case PoweredShelfType::UNCONNECTED:
				$result[] = $this;
				break;
		}

		return $result;
	}

	public function updateShelfConnection(?Block $origin = null) : void{
		$newType = PoweredShelfType::UNCONNECTED;
		$clockwise = Facing::rotateY($this->facing, true);
		$right = $this->getSide($clockwise);
		$left = $this->getSide(Facing::opposite($clockwise));

		if($this->powered){
			$connectRight = $right instanceof self && $right->canConnect($this);
			$connectLeft = $left instanceof self && $left->canConnect($this);

			if($connectLeft && !$connectRight){
				$newType = PoweredShelfType::LEFT;
			}elseif(!$connectLeft && $connectRight){
				$newType = PoweredShelfType::RIGHT;
			}elseif($connectLeft){
				$newType = $this->determineCenterType($left, $right);
			}
		}

		if($newType !== $this->getShelfType()){
			$this->shelfType = $newType->value;
			$world = $this->position->getWorld();
			$world->setBlock($this->position, $this);

			if($right !== $origin && $right instanceof self){
				$right->updateShelfConnection($this);
			}
			if($left !== $origin && $left instanceof self){
				$left->updateShelfConnection($this);
			}
		}
	}

	private function canConnect(self $shelf) : bool{
		if(!$this->powered || $this->facing !== $shelf->facing){
			return false;
		}

		return match($this->getShelfType()){
			PoweredShelfType::LEFT => $this->canConnectToSide($shelf, Facing::opposite(Facing::rotateY($this->facing, true)), PoweredShelfType::RIGHT),
			PoweredShelfType::RIGHT => $this->canConnectToSide($shelf, Facing::rotateY($this->facing, true), PoweredShelfType::LEFT),
			PoweredShelfType::CENTER, PoweredShelfType::UNCONNECTED => true,
		};
	}

	private function canConnectToSide(self $shelf, int $side, PoweredShelfType $expectedType) : bool{
		$sideBlock = $this->getSide($side);
		if($sideBlock->position->equals($shelf->position)){
			return true;
		}

		return $sideBlock instanceof self && $sideBlock->getShelfType() === $expectedType;
	}

	private function determineCenterType(Block $left, Block $right) : PoweredShelfType{
		if($right instanceof self && $right->getShelfType() === PoweredShelfType::UNCONNECTED && $right->canConnect($this)){
			return PoweredShelfType::LEFT;
		}
		if($left instanceof self && $left->getShelfType() === PoweredShelfType::UNCONNECTED && $left->canConnect($this)){
			return PoweredShelfType::RIGHT;
		}

		$rightIsRight = $right instanceof self && $right->getShelfType() === PoweredShelfType::RIGHT;
		$leftIsLeft = $left instanceof self && $left->getShelfType() === PoweredShelfType::LEFT;
		if($rightIsRight && $leftIsLeft){
			return PoweredShelfType::RIGHT;
		}

		$rightIsCenter = $right instanceof self && $right->getShelfType() === PoweredShelfType::CENTER;
		$leftIsCenter = $left instanceof self && $left->getShelfType() === PoweredShelfType::CENTER;
		if($rightIsCenter){
			return PoweredShelfType::RIGHT;
		}
		if($leftIsCenter){
			return PoweredShelfType::LEFT;
		}

		return PoweredShelfType::CENTER;
	}
}
