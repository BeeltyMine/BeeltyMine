<?php

declare(strict_types=1);

namespace pocketmine\block\tile;

use pocketmine\math\Facing;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;

final class PistonArm extends Spawnable{
	public const TAG_MOVABLE = "isMovable";
	public const TAG_PROGRESS = "Progress";
	public const TAG_LAST_PROGRESS = "LastProgress";
	public const TAG_ATTACHED_BLOCKS = "AttachedBlocks";
	public const TAG_BREAK_BLOCKS = "BreakBlocks";
	public const TAG_STICKY = "Sticky";
	public const TAG_STATE = "State";
	public const TAG_NEW_STATE = "NewState";
	public const TAG_POWERED = "powered";
	public const TAG_FACING = "facing";
	public const TAG_EXTENDING = "Extending";

	private bool $movable = true;
	private float $progress = 0.0;
	private float $lastProgress = 0.0;
	/** @var int[] */
	private array $attachedBlocks = [];
	private bool $sticky = false;
	private int $state = 0;
	private int $newState = 0;
	private bool $powered = false;
	private int $facing = Facing::NORTH;
	private bool $extending = false;

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$this->writeAllData($nbt);
		$nbt->setTag(self::TAG_BREAK_BLOCKS, new ListTag([], NBT::TAG_Int));
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->movable = $nbt->getByte(self::TAG_MOVABLE, 1) !== 0;
		$this->progress = $nbt->getFloat(self::TAG_PROGRESS, 0.0);
		$this->lastProgress = $nbt->getFloat(self::TAG_LAST_PROGRESS, 0.0);
		$this->sticky = $nbt->getByte(self::TAG_STICKY, 0) !== 0;
		$this->state = $nbt->getByte(self::TAG_STATE, 0);
		$this->newState = $nbt->getByte(self::TAG_NEW_STATE, 0);
		$this->powered = $nbt->getByte(self::TAG_POWERED, 0) !== 0;
		$this->facing = $nbt->getInt(self::TAG_FACING, Facing::NORTH);
		$this->extending = $nbt->getByte(self::TAG_EXTENDING, 0) !== 0;

		$attached = $nbt->getListTag(self::TAG_ATTACHED_BLOCKS, IntTag::class);
		$this->attachedBlocks = [];
		if($attached !== null){
			foreach($attached->getValue() as $coordTag){
				$this->attachedBlocks[] = $coordTag->getValue();
			}
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->writeAllData($nbt);
	}

	public function setVisualState(int $facing, bool $sticky, bool $powered, bool $extended) : void{
		Facing::validate($facing);

		$this->facing = $facing;
		$this->sticky = $sticky;
		$this->powered = $powered;
		$this->extending = $extended;
		$this->movable = !$extended;
		$this->progress = $extended ? 1.0 : 0.0;
		$this->lastProgress = $this->progress;
		$this->state = $extended ? 2 : 0;
		$this->newState = $this->state;
		$this->attachedBlocks = [];

		$this->clearSpawnCompoundCache();
	}

	private function writeAllData(CompoundTag $nbt) : void{
		$nbt->setByte(self::TAG_MOVABLE, $this->movable ? 1 : 0);
		$nbt->setFloat(self::TAG_PROGRESS, $this->progress);
		$nbt->setFloat(self::TAG_LAST_PROGRESS, $this->lastProgress);
		$nbt->setTag(self::TAG_ATTACHED_BLOCKS, $this->createAttachedBlocksTag());
		$nbt->setByte(self::TAG_STICKY, $this->sticky ? 1 : 0);
		$nbt->setByte(self::TAG_STATE, $this->state);
		$nbt->setByte(self::TAG_NEW_STATE, $this->newState);
		$nbt->setByte(self::TAG_POWERED, $this->powered ? 1 : 0);
		$nbt->setInt(self::TAG_FACING, $this->facing);
		$nbt->setByte(self::TAG_EXTENDING, $this->extending ? 1 : 0);
	}

	private function createAttachedBlocksTag() : ListTag{
		$list = new ListTag([], NBT::TAG_Int);
		foreach($this->attachedBlocks as $coord){
			$list->push(new IntTag($coord));
		}

		return $list;
	}
}