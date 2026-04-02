<?php

declare(strict_types=1);

namespace pocketmine\block\inventory;

use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\inventory\TemporaryInventory;
use pocketmine\inventory\transaction\action\validator\CallbackSlotValidator;
use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\world\beacon\BeaconEffects;

final class BeaconInventory extends SimpleInventory implements BlockInventory, TemporaryInventory{
	use BlockInventoryTrait;

	public const SLOT_PAYMENT = 0;

	private Item $pendingPayment;
	private int $pendingPrimaryEffect = 0;
	private int $pendingSecondaryEffect = 0;

	public function __construct(
		private BeaconTile $beacon
	){
		$this->holder = $beacon->getPosition();
		parent::__construct(1);
		$this->setMaxStackSize(1);
		$this->pendingPayment = VanillaItems::AIR();

		$this->validators->add(new CallbackSlotValidator(self::validatePayment(...)));
	}

	public function getBeacon() : BeaconTile{
		return $this->beacon;
	}

	public function getPaymentItem() : Item{
		return $this->getItem(self::SLOT_PAYMENT);
	}

	public function rememberPendingPayment(Item $item) : void{
		$this->pendingPayment = $item->isNull() ? VanillaItems::AIR() : clone $item;
	}

	public function getPendingPayment() : Item{
		return clone $this->pendingPayment;
	}

	public function hasPendingPayment() : bool{
		return !$this->pendingPayment->isNull();
	}

	public function clearPendingPayment() : void{
		$this->pendingPayment = VanillaItems::AIR();
	}

	public function restorePendingPaymentToSlot() : void{
		if($this->pendingPayment->isNull()){
			return;
		}

		$this->setItem(self::SLOT_PAYMENT, $this->pendingPayment);
		$this->pendingPayment = VanillaItems::AIR();
	}

	public function setPendingSelection(int $primaryEffect, int $secondaryEffect) : void{
		$this->pendingPrimaryEffect = $primaryEffect;
		$this->pendingSecondaryEffect = $secondaryEffect;
	}

	public function hasPendingSelection() : bool{
		return $this->pendingPrimaryEffect !== 0;
	}

	public function getPendingPrimaryEffect() : int{
		return $this->pendingPrimaryEffect;
	}

	public function getPendingSecondaryEffect() : int{
		return $this->pendingSecondaryEffect;
	}

	public function clearPendingSelection() : void{
		$this->pendingPrimaryEffect = 0;
		$this->pendingSecondaryEffect = 0;
	}

	public function onClose(Player $who) : void{
		parent::onClose($who);

		if(!$this->pendingPayment->isNull()){
			foreach($who->getInventory()->addItem($this->pendingPayment) as $leftover){
				$who->dropItem($leftover);
			}
			$this->pendingPayment = VanillaItems::AIR();
		}

		$this->clearPendingSelection();
	}

	private static function validatePayment(Inventory $inventory, Item $item, int $slot) : ?TransactionValidationException{
		if($slot !== self::SLOT_PAYMENT){
			return new TransactionValidationException("Beacon only has one payment slot");
		}

		if(!BeaconEffects::isValidPaymentItem($item)){
			return new TransactionValidationException("Item cannot be used as beacon payment");
		}

		return null;
	}
}