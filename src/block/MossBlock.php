<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\BlockEventHelper;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use function abs;
use function max;
use function mt_rand;

final class MossBlock extends Opaque{

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!($item instanceof Fertilizer) || !$this->getSide(Facing::UP)->canBeReplaced()){
			return false;
		}

		$item->pop();
		$this->spread(new Random(mt_rand()));
		return true;
	}

	private function spread(Random $random) : void{
		$world = $this->position->getWorld();

		for($x = -3; $x <= 3; ++$x){
			for($y = -1; $y <= 2; ++$y){
				for($z = -3; $z <= 3; ++$z){
					$distance = max(abs($x), abs($z), abs($y));
					if($distance > 0 && $random->nextBoundedInt($distance + 1) !== 0){
						continue;
					}

					$targetPos = $this->position->add($x, $y, $z);
					if(!$world->isInWorld($targetPos->x, $targetPos->y, $targetPos->z)){
						continue;
					}

					$target = $world->getBlock($targetPos);
					$above = $world->getBlock($targetPos->up());
					if(!$above->canBeReplaced()){
						continue;
					}

					if($this->canConvert($target)){
						BlockEventHelper::spread($target, VanillaBlocks::MOSS_BLOCK(), $this);
					}

					$ground = $world->getBlock($targetPos);
					if($ground->getTypeId() !== BlockTypeIds::MOSS_BLOCK){
						continue;
					}

					$plant = match($random->nextBoundedInt(10)){
						0 => VanillaBlocks::FLOWERING_AZALEA(),
						1 => VanillaBlocks::AZALEA(),
						default => VanillaBlocks::MOSS_CARPET()
					};
					if($plant->canBePlacedAt($above, Vector3::zero(), Facing::DOWN, true)){
						$world->setBlock($targetPos->up(), $plant);
					}
				}
			}
		}
	}

	private function canConvert(Block $block) : bool{
		if($block->hasTypeTag(BlockTypeTags::DIRT) || $block->hasTypeTag(BlockTypeTags::MUD)){
			return true;
		}

		return match($block->getTypeId()){
			BlockTypeIds::STONE,
			BlockTypeIds::DEEPSLATE,
			BlockTypeIds::TUFF,
			BlockTypeIds::ANDESITE,
			BlockTypeIds::DIORITE,
			BlockTypeIds::GRANITE,
			BlockTypeIds::CALCITE => true,
			default => false,
		};
	}
}
