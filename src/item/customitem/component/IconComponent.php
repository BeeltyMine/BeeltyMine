<?php
declare(strict_types=1);

namespace pocketmine\item\customitem\component;

final class IconComponent implements ItemComponent {

	private string $defaultTexture;
	private string $dyedTexture;
	private string $trimTexture;

	/**
	 * Determines the icon to represent the item in the UI and elsewhere.
	 * @param string $defaultTexture the texture name should match the `resource_pack/textures/item_texture.json` `texture_data` key
	 * @param string $dyedTexture optional dyed texture key
	 * @param string $trimTexture optional trim texture key
	 */
	public function __construct(string $defaultTexture, string $dyedTexture = "", string $trimTexture = ""){
		$this->defaultTexture = $defaultTexture;
		$this->dyedTexture = $dyedTexture;
		$this->trimTexture = $trimTexture;
	}

	public function getName() : string{
		return "minecraft:icon";
	}

	public function getValue() : array{
		return [
			"texture" => $this->defaultTexture,
			"textures" => [
				"default" => $this->defaultTexture,
				"dyed" => $this->dyedTexture,
				"icon_trim" => $this->trimTexture
			]
		];
	}

	public function isProperty() : bool{
		return true;
	}
}