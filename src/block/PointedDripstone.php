<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\Fallable;
use pocketmine\block\utils\PointedDripstoneThickness;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\object\FallingBlock;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\Sound;

use function max;

final class PointedDripstone extends Transparent implements Fallable{
	private PointedDripstoneThickness $thickness = PointedDripstoneThickness::TIP;
	private bool $hanging = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->enum($this->thickness);
		$w->bool($this->hanging);
	}

	public function getThickness() : PointedDripstoneThickness{
		return $this->thickness;
	}

	/** @return $this */
	public function setThickness(PointedDripstoneThickness $thickness) : self{
		$this->thickness = $thickness;
		return $this;
	}

	public function isHanging() : bool{
		return $this->hanging;
	}

	/** @return $this */
	public function setHanging(bool $hanging) : self{
		$this->hanging = $hanging;
		return $this;
	}

	private function getSupportFacing() : int{
		return $this->hanging ? Facing::UP : Facing::DOWN;
	}

	private function getTipFacing() : int{
		return Facing::opposite($this->getSupportFacing());
	}

	private function canBeSupportedAt(Block $block) : bool{
		$supportFacing = $this->getSupportFacing();
		$support = $block->getSide($supportFacing);
		return $support->getSupportType(Facing::opposite($supportFacing)) === SupportType::FULL || self::isSameOrientation($support, $this->hanging);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($face !== Facing::UP && $face !== Facing::DOWN){
			return false;
		}
		$this->hanging = $face === Facing::DOWN;
		$this->thickness = PointedDripstoneThickness::TIP;
		if(!$this->canBeSupportedAt($blockReplace)){
			return false;
		}

		$x = $blockReplace->position->getFloorX();
		$y = $blockReplace->position->getFloorY();
		$z = $blockReplace->position->getFloorZ();

		$tx->addBlockAt($x, $y, $z, clone $this);
		$this->updateThicknessInTransaction($tx, $x, $y, $z);

		$supportOffset = Facing::OFFSET[$this->getSupportFacing()];
		$this->updateThicknessInTransaction($tx, $x + $supportOffset[0], $y + $supportOffset[1], $z + $supportOffset[2]);

		$tipOffset = Facing::OFFSET[$this->getTipFacing()];
		$this->updateThicknessInTransaction($tx, $x + $tipOffset[0], $y + $tipOffset[1], $z + $tipOffset[2]);

		return true;
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this)){
			if($this->hanging){
				$this->collapseHangingDripstone();
			}else{
				$world = $this->position->getWorld();
				$world->setBlock($this->position, VanillaBlocks::AIR());
				$world->dropItem($this->position->add(0.5, 0.5, 0.5), $this->asItem());
			}
			return;
		}

		$newThickness = $this->resolveThicknessFromWorld();
		if($newThickness !== $this->thickness){
			$this->position->getWorld()->setBlock($this->position, (clone $this)->setThickness($newThickness));
		}
	}

	public function tickFalling() : ?Block{
		return null;
	}

	public function onHitGround(FallingBlock $blockEntity) : bool{
		return false;
	}

	public function getFallDamagePerBlock() : float{
		return 1.0;
	}

	public function getMaxFallDamage() : float{
		return 40.0;
	}

	public function getLandSound() : ?Sound{
		return null;
	}

	public function onEntityLand(Entity $entity) : ?float{
		if(!$this->hanging && $this->thickness === PointedDripstoneThickness::TIP){
			$fallDistance = $entity->getFallDistance();
			if($fallDistance > 0){
				$entity->setFallDistance(max($fallDistance, ($fallDistance * 2.0) + 1.0));
			}
		}

		return null;
	}

	protected function recalculateCollisionBoxes() : array{
		$box = AxisAlignedBB::one();
		$box->squash(Axis::X, 6 / 16);
		$box->squash(Axis::Z, 6 / 16);
		$box->trim($this->hanging ? Facing::DOWN : Facing::UP, 8 / 16);
		return [$box];
	}

	private function updateThicknessInTransaction(BlockTransaction $tx, int $x, int $y, int $z) : void{
		$block = $tx->fetchBlockAt($x, $y, $z);
		if(!$block instanceof self){
			return;
		}

		$tx->addBlockAt($x, $y, $z, (clone $block)->setThickness($this->resolveThicknessFromTransaction($tx, $x, $y, $z, $block->hanging)));
	}

	private function resolveThicknessFromWorld() : PointedDripstoneThickness{
		$supportFacing = $this->getSupportFacing();
		$tipFacing = $this->getTipFacing();

		$support = $this->getSide($supportFacing);
		$tip = $this->getSide($tipFacing);

		if(self::isOppositeOrientation($tip, $this->hanging)){
			return PointedDripstoneThickness::MERGE;
		}
		if(!self::isSameOrientation($tip, $this->hanging)){
			return PointedDripstoneThickness::TIP;
		}
		if(!self::isSameOrientation($support, $this->hanging)){
			return PointedDripstoneThickness::BASE;
		}

		$tipTail = $tip->getSide($tipFacing);
		return self::isSameOrientation($tipTail, $this->hanging) ? PointedDripstoneThickness::MIDDLE : PointedDripstoneThickness::FRUSTUM;
	}

	private function resolveThicknessFromTransaction(BlockTransaction $tx, int $x, int $y, int $z, bool $hanging) : PointedDripstoneThickness{
		$supportFacing = $hanging ? Facing::UP : Facing::DOWN;
		$tipFacing = Facing::opposite($supportFacing);

		$supportOffset = Facing::OFFSET[$supportFacing];
		$tipOffset = Facing::OFFSET[$tipFacing];

		$support = $tx->fetchBlockAt($x + $supportOffset[0], $y + $supportOffset[1], $z + $supportOffset[2]);
		$tip = $tx->fetchBlockAt($x + $tipOffset[0], $y + $tipOffset[1], $z + $tipOffset[2]);

		if(self::isOppositeOrientation($tip, $hanging)){
			return PointedDripstoneThickness::MERGE;
		}
		if(!self::isSameOrientation($tip, $hanging)){
			return PointedDripstoneThickness::TIP;
		}
		if(!self::isSameOrientation($support, $hanging)){
			return PointedDripstoneThickness::BASE;
		}

		$tipTail = $tx->fetchBlockAt($x + ($tipOffset[0] * 2), $y + ($tipOffset[1] * 2), $z + ($tipOffset[2] * 2));
		return self::isSameOrientation($tipTail, $hanging) ? PointedDripstoneThickness::MIDDLE : PointedDripstoneThickness::FRUSTUM;
	}

	private static function isSameOrientation(Block $block, bool $hanging) : bool{
		return $block instanceof self && $block->hanging === $hanging;
	}

	private static function isOppositeOrientation(Block $block, bool $hanging) : bool{
		return $block instanceof self && $block->hanging !== $hanging;
	}

	private function collapseHangingDripstone() : void{
		$world = $this->position->getWorld();
		$current = $this;

		while($current instanceof self && $current->hanging){
			$position = $current->position;
			$next = $world->getBlock($position->getSide(Facing::DOWN));

			$world->setBlock($position, VanillaBlocks::AIR());

			$fall = new FallingBlock(Location::fromObject($position->add(0.5, 0, 0.5), $world), clone $current);
			$fall->spawnToAll();

			$current = $next;
		}
	}
}
