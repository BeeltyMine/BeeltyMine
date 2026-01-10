<?php

declare(strict_types=1);

namespace examples\customitems;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\customitem\CustomItemDefinition;
use pocketmine\item\customitem\CustomItemManager;
use pocketmine\item\customitem\CustomItemFactory;
use pocketmine\item\customitem\CustomItemRegistrar;
use pocketmine\item\customitem\component\IconComponent;
use pocketmine\item\customitem\component\MaxStackSizeComponent;
use pocketmine\item\customitem\component\DisplayNameComponent;
use pocketmine\item\customitem\component\RarityComponent;
use pocketmine\item\customitem\component\FoodComponent;
use pocketmine\item\customitem\component\UseAnimationComponent;
use pocketmine\item\customitem\component\DurabilityComponent;
use pocketmine\item\customitem\component\DamageComponent;
use pocketmine\item\customitem\component\GlintComponent;
use pocketmine\item\customitem\data\CreativeCategory;
use pocketmine\world\format\io\GlobalItemDataHandlers;

class CustomItemExample extends PluginBase{
	
	public function onEnable() : void{
		$this->getLogger()->info("Custom Items yükleniyor...");
		$this->registerCustomItems();
		$this->getLogger()->info("Custom Items yüklendi!");
	}
	
	private function registerCustomItems() : void{
		$serializer = GlobalItemDataHandlers::getSerializer();
		$deserializer = GlobalItemDataHandlers::getDeserializer();
		
-		$customSword = new CustomItemDefinition(
			identifier: "example:ruby_sword",
			numericId: 10000,
			displayName: "Yakut Kılıç",
			components: [
				new IconComponent("ruby_sword"),
				new DisplayNameComponent("§c§lYakut Kılıç"),
				new DamageComponent(12),
				new DurabilityComponent(2500),
				new MaxStackSizeComponent(1),
				new RarityComponent(RarityComponent::EPIC),
				new GlintComponent(true)
			],
			creativeCategory: CreativeCategory::EQUIPMENT
		);
		
		CustomItemRegistrar::register($customSword, $serializer, $deserializer, "ruby_sword");
		
	}
}
