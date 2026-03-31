<?php


declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\block\utils\WallConnectionType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class PaleMossCarpet extends Flowable{
	use StaticSupportTrait;

	/**
	 * @var WallConnectionType[]
	 * @phpstan-var array<Facing::NORTH|Facing::EAST|Facing::SOUTH|Facing::WEST, WallConnectionType>
	 */
	private array $connections = [];

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->wallConnections($this->connections);
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getSide(Facing::DOWN)->getTypeId() !== BlockTypeIds::AIR;
	}

	public function getConnection(int $face) : ?WallConnectionType{
		return $this->connections[$face] ?? null;
	}

	/** @return $this */
	public function setConnection(int $face, ?WallConnectionType $type) : self{
		if($type === null){
			unset($this->connections[$face]);
		}else{
			$this->connections[$face] = $type;
		}
		return $this;
	}

	public function onNearbyBlockChange() : void{
		$oldConnections = $this->connections;
		parent::onNearbyBlockChange();
		if($this->position->getWorld()->getBlock($this->position)->getTypeId() !== $this->getTypeId()){
			return;
		}

		if($this->recalculateConnections() && $oldConnections !== $this->connections){
			$this->position->getWorld()->setBlock($this->position, $this, false);
		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!($item instanceof Fertilizer)){
			return false;
		}

		$grew = false;
		foreach(Facing::HORIZONTAL as $horizontal){
			if($this->getConnection($horizontal) === WallConnectionType::SHORT && $this->isTallConnection($horizontal)){
				$this->connections[$horizontal] = WallConnectionType::TALL;
				$grew = true;
			}
		}

		if($grew){
			$item->pop();
			$this->position->getWorld()->setBlock($this->position, $this);
		}

		return $grew;
	}

	private function recalculateConnections() : bool{
		$changed = false;
		foreach(Facing::HORIZONTAL as $face){
			$newType = null;
			$side = $this->getSide($face);
			if($side->getSupportType(Facing::opposite($face)) === SupportType::FULL || !$side->canBeReplaced()){
				$newType = $this->isTallConnection($face) ? WallConnectionType::TALL : WallConnectionType::SHORT;
			}

			if(($this->connections[$face] ?? null) !== $newType){
				$this->setConnection($face, $newType);
				$changed = true;
			}
		}

		return $changed;
	}

	private function isTallConnection(int $face) : bool{
		$side = $this->getSide($face);
		$above = $side->getSide(Facing::UP);
		return $above->getSupportType(Facing::opposite($face)) === SupportType::FULL || !$above->canBeReplaced();
	}

	public function isSolid() : bool{
		return true;
	}

	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::UP, 15 / 16)];
	}

	public function getFlameEncouragement() : int{
		return 30;
	}

	public function getFlammability() : int{
		return 60;
	}
}
