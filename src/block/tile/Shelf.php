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

namespace pocketmine\block\tile;

use pocketmine\block\Shelf as BlockShelf;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\World;

class Shelf extends Spawnable implements Container{
	use ContainerTrait;

	private SimpleInventory $inventory;

	public function __construct(World $world, \pocketmine\math\Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new SimpleInventory(3);
		$this->inventory->getListeners()->add(CallbackInventoryListener::onAnyChange(
			function(Inventory $unused) use ($world, $pos) : void{
				$this->clearSpawnCompoundCache();
				$block = $world->getBlock($pos);
				if($block instanceof BlockShelf){
					$world->setBlock($pos, $block);
				}
			}
		));
	}

	public function getInventory() : SimpleInventory{
		return $this->inventory;
	}

	public function getRealInventory() : SimpleInventory{
		return $this->inventory;
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadItems($nbt);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveItems($nbt);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$items = [];
		foreach($this->inventory->getContents(true) as $slot => $item){
			if($item->isNull()){
				$items[$slot] = CompoundTag::create()
					->setByte(SavedItemStackData::TAG_COUNT, 0)
					->setByte(SavedItemStackData::TAG_SLOT, $slot)
					->setShort(SavedItemData::TAG_DAMAGE, 0)
					->setString(SavedItemData::TAG_NAME, "")
					->setByte(SavedItemStackData::TAG_WAS_PICKED_UP, 0);
				continue;
			}
			$itemTag = TypeConverter::getInstance()->getItemTranslator()->toNetworkNbt($item);
			$itemTag->setByte(SavedItemStackData::TAG_SLOT, $slot);
			$items[$slot] = $itemTag;
		}

		$nbt->setTag(Container::TAG_ITEMS, new ListTag($items, NBT::TAG_Compound));
	}
}
