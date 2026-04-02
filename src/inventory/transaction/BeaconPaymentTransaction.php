<?php

declare(strict_types=1);

namespace pocketmine\inventory\transaction;

use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\world\beacon\BeaconEffects;
use pocketmine\world\beacon\BeaconStructure;
use function count;

final class BeaconPaymentTransaction extends InventoryTransaction{
	public function __construct(
		Player $source,
		private BeaconTile $beacon,
		private int $primaryEffectId,
		private int $secondaryEffectId
	){
		parent::__construct($source);
	}

	private function getCurrentBeaconLevel() : int{
		$world = $this->beacon->getPosition()->getWorld();
		$pos = $this->beacon->getPosition();

		return BeaconStructure::calculateLevel(function(int $layer, int $offsetX, int $offsetZ) use ($world, $pos) : bool{
			return BeaconStructure::isValidBaseBlockTypeId($world->getBlockAt($pos->getFloorX() + $offsetX, $pos->getFloorY() - $layer, $pos->getFloorZ() + $offsetZ)->getTypeId());
		});
	}

	public function validate() : void{
		$this->squashDuplicateSlotChanges();

		$createdItems = [];
		$consumedItems = [];
		$this->matchItems($createdItems, $consumedItems);
		if(count($this->actions) === 0){
			throw new TransactionValidationException("Beacon payment transaction must have at least one action to be executable");
		}

		if(count($createdItems) > 0){
			throw new TransactionValidationException("Beacon payment cannot create items");
		}

		if(count($consumedItems) !== 1){
			throw new TransactionValidationException("Beacon payment must consume exactly one payment item");
		}

		$paymentItem = $consumedItems[0];
		if($paymentItem->getCount() !== 1 || !BeaconEffects::isValidPaymentItem($paymentItem)){
			throw new TransactionValidationException("Invalid beacon payment item");
		}

		$level = $this->getCurrentBeaconLevel();
		if(!BeaconEffects::isPrimaryEffectAllowed($level, $this->primaryEffectId)){
			throw new TransactionValidationException("Primary beacon effect is not allowed for the current beacon level");
		}

		if(!BeaconEffects::isSecondaryEffectAllowed($level, $this->primaryEffectId, $this->secondaryEffectId)){
			throw new TransactionValidationException("Secondary beacon effect is not allowed for the current beacon level");
		}
	}

	public function execute() : void{
		parent::execute();

		$this->beacon->setPrimaryEffect($this->primaryEffectId);
		$this->beacon->setSecondaryEffect($this->secondaryEffectId);
	}
}