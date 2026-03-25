<?php

declare(strict_types=1);

namespace pocketmine\ddui;

use pocketmine\ddui\element\LayoutElement;
use pocketmine\ddui\properties\DataDrivenProperty;
use pocketmine\ddui\properties\ObjectProperty;
use pocketmine\network\mcpe\protocol\ClientboundDataDrivenUICloseScreenPacket;
use pocketmine\network\mcpe\protocol\ClientboundDataDrivenUIShowScreenPacket;
use pocketmine\network\mcpe\protocol\ClientboundDataStorePacket;
use pocketmine\network\mcpe\protocol\types\DataStoreChange;
use pocketmine\network\mcpe\protocol\types\StringDataStoreValue;
use pocketmine\player\Player;
use SplObjectStorage;
use WeakMap;

abstract class DataDrivenScreen extends ObjectProperty{
	/** @var WeakMap<Player, DataDrivenScreen>|null */
	private static ?WeakMap $activeScreens = null;

	/** @var SplObjectStorage<Player, null> */
	private SplObjectStorage $viewers;

	protected LayoutElement $layout;

	abstract public function getIdentifier() : string;

	abstract public function getDataProperty() : string;

	public function __construct(){
		parent::__construct("");
		$this->viewers = new SplObjectStorage();
		$this->layout = new LayoutElement($this);
		$this->setProperty($this->layout);
	}

	public function show(Player $player) : void{
		$activeScreen = self::getActiveScreen($player);
		if($activeScreen !== null && $activeScreen !== $this){
			$activeScreen->handleClosed($player);
		}

		[$dataStore] = explode(":", $this->getIdentifier(), 2);
		$session = $player->getNetworkSession();

		$session->sendDataPacket(ClientboundDataStorePacket::create([
			new DataStoreChange(
				$dataStore,
				$this->getDataProperty(),
				1,
				new StringDataStoreValue($this->toPropertyValue())
			)
		]));
		$session->sendDataPacket(ClientboundDataDrivenUIShowScreenPacket::create($this->getIdentifier(), 0, null));

		$this->viewers->attach($player);
		self::getActiveScreenMap()[$player] = $this;
	}

	public function close(Player $player) : void{
		$this->handleClosed($player);
		$player->getNetworkSession()->sendDataPacket(ClientboundDataDrivenUICloseScreenPacket::create(0));
	}

	public function handleClosed(Player $player) : void{
		if($this->viewers->contains($player)){
			$this->viewers->detach($player);
		}

		$activeScreens = self::getActiveScreenMap();
		if(isset($activeScreens[$player]) && $activeScreens[$player] === $this){
			unset($activeScreens[$player]);
		}
	}

	/**
	 * @return list<Player>
	 */
	public function getAllViewers() : array{
		$viewers = [];
		foreach($this->viewers as $viewer){
			$viewers[] = $viewer;
		}

		return $viewers;
	}

	public static function getActiveScreen(Player $player) : ?self{
		$activeScreens = self::getActiveScreenMap();
		return isset($activeScreens[$player]) ? $activeScreens[$player] : null;
	}

	public function resolvePath(string $path) : ?DataDrivenProperty{
		if($path === ""){
			return $this;
		}

		$current = $this;
		$offset = 0;
		$pathLength = strlen($path);

		while($offset < $pathLength){
			if($path[$offset] === "."){
				++$offset;
				continue;
			}

			if($path[$offset] === "["){
				$end = strpos($path, "]", $offset + 1);
				if($end === false){
					return null;
				}

				$token = substr($path, $offset + 1, $end - ($offset + 1));
				$offset = $end + 1;
			}else{
				$nextOffset = $offset;
				while($nextOffset < $pathLength && $path[$nextOffset] !== "." && $path[$nextOffset] !== "["){
					++$nextOffset;
				}

				$token = substr($path, $offset, $nextOffset - $offset);
				$offset = $nextOffset;
			}

			if(!$current instanceof ObjectProperty){
				return null;
			}

			$current = $current->getProperty($token);
			if($current === null){
				return null;
			}
		}

		return $current;
	}

	public function getRootScreen() : self{
		return $this;
	}

	public function toPropertyValue() : string{
		return (string) json_encode($this->toSchemaValue(), JSON_THROW_ON_ERROR);
	}

	private static function getActiveScreenMap() : WeakMap{
		return self::$activeScreens ??= new WeakMap();
	}
}
