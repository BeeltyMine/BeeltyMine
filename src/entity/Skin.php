<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\entity;

use Ahc\Json\Comment as CommentedJsonDecoder;
use pocketmine\utils\Limits;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function explode;
use function intdiv;
use function str_replace;
use function strlen;
use function str_repeat;
use function substr;
use function sqrt;
use const JSON_THROW_ON_ERROR;

final class Skin{
	public const ACCEPTED_SKIN_SIZES = [
		32 * 32 * 4,
		64 * 32 * 4,
		64 * 64 * 4,
		128 * 64 * 4,
		128 * 128 * 4,
		256 * 128 * 4,
		256 * 256 * 4
	];
	public const LEGACY_CAPE_IMAGE_WIDTH = 64;
	public const LEGACY_CAPE_IMAGE_HEIGHT = 32;
	public const ARM_SIZE_WIDE = "wide";
	public const ARM_SIZE_SLIM = "slim";
	public const DEFAULT_GEOMETRY_NAME = "geometry.humanoid.custom";
	public const DEFAULT_SLIM_GEOMETRY_NAME = "geometry.humanoid.customSlim";

	private string $skinId;
	private string $skinData;
	private string $capeData;
	private string $geometryName;
	private string $geometryData;
	private int $skinImageWidth;
	private int $skinImageHeight;
	private int $capeImageWidth;
	private int $capeImageHeight;
	private string $skinResourcePatch = "";
	private string $geometryDataEngineVersion = "";
	private string $animationData = "";
	private string $capeId = "";
	private ?string $fullSkinId = null;
	private string $armSize = self::ARM_SIZE_WIDE;
	private string $skinColor = "";
	private string $playFabId = "";
	/** @var array<int, mixed> */
	private array $animations = [];
	/** @var array<int, mixed> */
	private array $personaPieces = [];
	/** @var array<int, mixed> */
	private array $pieceTintColors = [];
	private bool $premium = false;
	private bool $persona = false;
	private bool $personaCapeOnClassic = false;
	private bool $trusted = true;
	private bool $primaryUser = true;
	private bool $override = true;

	private static function checkLength(string $string, string $name, int $maxLength) : void{
		if(strlen($string) > $maxLength){
			throw new InvalidSkinException("$name must be at most $maxLength bytes, but have " . strlen($string) . " bytes");
		}
	}

	private static function isSupportedSkinDimension(int $dimension) : bool{
		return $dimension >= 32 && $dimension <= 256 && ($dimension & ($dimension - 1)) === 0;
	}

	/**
	 * @phpstan-return array{0: int, 1: int}
	 */
	private static function inferLegacySkinImageSize(string $skinData) : array{
		$dataLength = strlen($skinData);
		if($dataLength % 4 !== 0){
			throw new InvalidSkinException("Invalid skin data size $dataLength bytes (must be divisible by 4)");
		}

		$pixelCount = intdiv($dataLength, 4);

		$squareSide = (int) sqrt($pixelCount);
		if($squareSide * $squareSide === $pixelCount && self::isSupportedSkinDimension($squareSide)){
			return [$squareSide, $squareSide];
		}

		if($pixelCount % 2 === 0){
			$rectHeight = (int) sqrt(intdiv($pixelCount, 2));
			if($rectHeight * $rectHeight * 2 === $pixelCount && self::isSupportedSkinDimension($rectHeight)){
				return [$rectHeight * 2, $rectHeight];
			}
		}

		throw new InvalidSkinException("Invalid skin data size $dataLength bytes (supported skins are power-of-two square or 2:1 images from 32x32 up to 256x256)");
	}

	private static function validateImageDimensions(string $name, int $width, int $height, string $data, bool $allowEmpty = false) : void{
		if($allowEmpty && $width === 0 && $height === 0 && $data === ""){
			return;
		}
		if($width <= 0 || $height <= 0){
			throw new InvalidSkinException("$name image dimensions must be positive");
		}
		$expected = $width * $height * 4;
		$actual = strlen($data);
		if($expected !== $actual){
			throw new InvalidSkinException("Invalid $name data size $actual bytes for image dimensions {$width}x{$height} (expected $expected bytes)");
		}
	}

	private static function getDefaultGeometryName(string $armSize) : string{
		return $armSize === self::ARM_SIZE_SLIM ? self::DEFAULT_SLIM_GEOMETRY_NAME : self::DEFAULT_GEOMETRY_NAME;
	}

	public static function createStandardFallback(?string $skinId = null, string $armSize = self::ARM_SIZE_WIDE) : self{
		$skin = new self(
			$skinId !== null && $skinId !== "" ? $skinId : "Standard_Custom",
			str_repeat("\x80\x80\x80\xff", 64 * 64),
			"",
			self::getDefaultGeometryName($armSize),
			""
		);
		$skin->setArmSize($armSize);
		return $skin;
	}

	private static function createResourcePatch(string $geometryName, string $armSize) : string{
		return json_encode([
			"geometry" => [
				"default" => $geometryName !== "" ? $geometryName : self::getDefaultGeometryName($armSize)
			]
		], JSON_THROW_ON_ERROR);
	}

	private static function extractGeometryNameFromResourcePatch(string $skinResourcePatch) : ?string{
		try{
			$resourcePatch = json_decode($skinResourcePatch, true, flags: JSON_THROW_ON_ERROR);
		}catch(\JsonException){
			return null;
		}

		if(is_array($resourcePatch) && isset($resourcePatch["geometry"]["default"]) && is_string($resourcePatch["geometry"]["default"])){
			return $resourcePatch["geometry"]["default"];
		}

		return null;
	}

	public function __construct(string $skinId, string $skinData, string $capeData = "", string $geometryName = "", string $geometryData = ""){
		self::checkLength($skinId, "Skin ID", Limits::INT16_MAX);
		self::checkLength($geometryName, "Geometry name", Limits::INT16_MAX);
		self::checkLength($geometryData, "Geometry data", Limits::INT32_MAX);

		if($skinId === ""){
			throw new InvalidSkinException("Skin ID must not be empty");
		}

		[$skinImageWidth, $skinImageHeight] = self::inferLegacySkinImageSize($skinData);
		[$capeImageWidth, $capeImageHeight] = $capeData === "" ? [0, 0] : [self::LEGACY_CAPE_IMAGE_WIDTH, self::LEGACY_CAPE_IMAGE_HEIGHT];
		self::validateImageDimensions("Cape", $capeImageWidth, $capeImageHeight, $capeData, true);

		if($geometryData !== ""){
			try{
				$decodedGeometry = (new CommentedJsonDecoder())->decode($geometryData);
			}catch(\RuntimeException $e){
				throw new InvalidSkinException("Invalid geometry data: " . $e->getMessage(), 0, $e);
			}

			/*
			 * Hack to cut down on network overhead due to skins, by un-pretty-printing geometry JSON.
			 *
			 * Mojang, some stupid reason, send every single model for every single skin in the selected skin-pack.
			 * Not only that, they are pretty-printed.
			 * TODO: find out what model crap can be safely dropped from the packet (unless it gets fixed first)
			 */
			$geometryData = json_encode($decodedGeometry, JSON_THROW_ON_ERROR);
		}

		$this->skinId = $skinId;
		$this->skinData = $skinData;
		$this->capeData = $capeData;
		$this->geometryName = $geometryName;
		$this->geometryData = $geometryData;
		$this->skinImageWidth = $skinImageWidth;
		$this->skinImageHeight = $skinImageHeight;
		$this->capeImageWidth = $capeImageWidth;
		$this->capeImageHeight = $capeImageHeight;
	}

	public function getSkinId() : string{
		return $this->skinId;
	}

	public function setSkinId(string $skinId) : void{
		self::checkLength($skinId, "Skin ID", Limits::INT16_MAX);
		if($skinId === ""){
			throw new InvalidSkinException("Skin ID must not be empty");
		}
		$this->skinId = $skinId;
	}

	public function getSkinData() : string{
		return $this->skinData;
	}

	public function getSkinImageWidth() : int{
		return $this->skinImageWidth;
	}

	public function getSkinImageHeight() : int{
		return $this->skinImageHeight;
	}

	public function setSkinImageDimensions(int $width, int $height) : void{
		self::validateImageDimensions("Skin", $width, $height, $this->skinData);
		$this->skinImageWidth = $width;
		$this->skinImageHeight = $height;
	}

	public function getCapeData() : string{
		return $this->capeData;
	}

	public function getCapeImageWidth() : int{
		return $this->capeImageWidth;
	}

	public function getCapeImageHeight() : int{
		return $this->capeImageHeight;
	}

	public function setCapeImageDimensions(int $width, int $height) : void{
		self::validateImageDimensions("Cape", $width, $height, $this->capeData, true);
		$this->capeImageWidth = $width;
		$this->capeImageHeight = $height;
	}

	public function getGeometryName() : string{
		return $this->geometryName;
	}

	public function getSkinResourcePatch() : string{
		return $this->skinResourcePatch !== "" ? $this->skinResourcePatch : self::createResourcePatch($this->geometryName, $this->armSize);
	}

	public function setSkinResourcePatch(string $skinResourcePatch) : void{
		self::checkLength($skinResourcePatch, "Skin resource patch", Limits::INT32_MAX);
		if(($geometryName = self::extractGeometryNameFromResourcePatch($skinResourcePatch)) !== null){
			$this->geometryName = $geometryName;
		}
		$this->skinResourcePatch = $skinResourcePatch;
	}

	public function getGeometryData() : string{
		return $this->geometryData;
	}

	public function getGeometryDataEngineVersion() : string{
		return $this->geometryDataEngineVersion;
	}

	public function setGeometryDataEngineVersion(string $geometryDataEngineVersion) : void{
		self::checkLength($geometryDataEngineVersion, "Geometry data engine version", Limits::INT16_MAX);
		$this->geometryDataEngineVersion = $geometryDataEngineVersion;
	}

	public function getAnimationData() : string{
		return $this->animationData;
	}

	public function setAnimationData(string $animationData) : void{
		self::checkLength($animationData, "Animation data", Limits::INT32_MAX);
		$this->animationData = $animationData;
	}

	public function getCapeId() : string{
		return $this->capeId;
	}

	public function setCapeId(string $capeId) : void{
		self::checkLength($capeId, "Cape ID", Limits::INT16_MAX);
		$this->capeId = $capeId;
	}

	public function getFullSkinId() : string{
		return $this->fullSkinId ?? ($this->skinId . $this->capeId);
	}

	public function setFullSkinId(string $fullSkinId) : void{
		self::checkLength($fullSkinId, "Full skin ID", Limits::INT16_MAX);
		$this->fullSkinId = $fullSkinId !== "" ? $fullSkinId : null;
	}

	public function getArmSize() : string{
		return $this->armSize;
	}

	public function setArmSize(string $armSize) : void{
		self::checkLength($armSize, "Arm size", Limits::INT16_MAX);
		$this->armSize = $armSize !== "" ? $armSize : self::ARM_SIZE_WIDE;
	}

	public function getSkinColor() : string{
		return $this->skinColor;
	}

	public function setSkinColor(string $skinColor) : void{
		self::checkLength($skinColor, "Skin color", Limits::INT16_MAX);
		$this->skinColor = $skinColor;
	}

	public function getPlayFabId() : string{
		if($this->playFabId === "" && $this->persona){
			$skinIdParts = explode("-", $this->skinId);
			if(isset($skinIdParts[5]) && $skinIdParts[5] !== ""){
				return $skinIdParts[5];
			}

			$fullSkinIdWithoutDashes = str_replace("-", "", $this->getFullSkinId());
			if(strlen($fullSkinIdWithoutDashes) > 16){
				return substr($fullSkinIdWithoutDashes, 16);
			}

			return $fullSkinIdWithoutDashes;
		}

		return $this->playFabId;
	}

	public function setPlayFabId(string $playFabId) : void{
		self::checkLength($playFabId, "PlayFab ID", Limits::INT16_MAX);
		$this->playFabId = $playFabId;
	}

	/**
	 * @return array<int, mixed>
	 */
	public function getAnimations() : array{
		return $this->animations;
	}

	/**
	 * @param array<int, mixed> $animations
	 */
	public function setAnimations(array $animations) : void{
		$this->animations = $animations;
	}

	/**
	 * @return array<int, mixed>
	 */
	public function getPersonaPieces() : array{
		return $this->personaPieces;
	}

	/**
	 * @param array<int, mixed> $personaPieces
	 */
	public function setPersonaPieces(array $personaPieces) : void{
		$this->personaPieces = $personaPieces;
	}

	/**
	 * @return array<int, mixed>
	 */
	public function getPieceTintColors() : array{
		return $this->pieceTintColors;
	}

	/**
	 * @param array<int, mixed> $pieceTintColors
	 */
	public function setPieceTintColors(array $pieceTintColors) : void{
		$this->pieceTintColors = $pieceTintColors;
	}

	public function isPremium() : bool{
		return $this->premium;
	}

	public function setPremium(bool $premium) : void{
		$this->premium = $premium;
	}

	public function isPersona() : bool{
		return $this->persona;
	}

	public function setPersona(bool $persona) : void{
		$this->persona = $persona;
	}

	public function isPersonaCapeOnClassic() : bool{
		return $this->personaCapeOnClassic;
	}

	public function setPersonaCapeOnClassic(bool $personaCapeOnClassic) : void{
		$this->personaCapeOnClassic = $personaCapeOnClassic;
	}

	public function isTrusted() : bool{
		return $this->trusted;
	}

	public function setTrusted(bool $trusted) : void{
		$this->trusted = $trusted;
	}

	public function isPrimaryUser() : bool{
		return $this->primaryUser;
	}

	public function setPrimaryUser(bool $primaryUser) : void{
		$this->primaryUser = $primaryUser;
	}

	public function isOverride() : bool{
		return $this->override;
	}

	public function setOverride(bool $override) : void{
		$this->override = $override;
	}
}
