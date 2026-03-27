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

namespace pocketmine\network\mcpe\convert;

use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use function is_array;
use function is_string;
use function json_decode;

class LegacySkinAdapter implements SkinAdapter{

	private static function getDefaultGeometryName(string $armSize) : string{
		return $armSize === Skin::ARM_SIZE_SLIM ? Skin::DEFAULT_SLIM_GEOMETRY_NAME : Skin::DEFAULT_GEOMETRY_NAME;
	}

	private static function extractGeometryName(string $resourcePatch, string $armSize) : string{
		$decodedResourcePatch = json_decode($resourcePatch, true);
		if(is_array($decodedResourcePatch) && isset($decodedResourcePatch["geometry"]["default"]) && is_string($decodedResourcePatch["geometry"]["default"])){
			return $decodedResourcePatch["geometry"]["default"];
		}

		return self::getDefaultGeometryName($armSize);
	}

	public function toSkinData(Skin $skin) : SkinData{
		$capeData = $skin->getCapeData();
		$capeImage = $capeData === "" ? new SkinImage(0, 0, "") : new SkinImage($skin->getCapeImageHeight(), $skin->getCapeImageWidth(), $capeData);
		return new SkinData(
			$skin->getSkinId(),
			$skin->getPlayFabId(),
			$skin->getSkinResourcePatch(),
			new SkinImage($skin->getSkinImageHeight(), $skin->getSkinImageWidth(), $skin->getSkinData()),
			$skin->getAnimations(),
			$capeImage,
			$skin->getGeometryData(),
			$skin->getGeometryDataEngineVersion(),
			$skin->getAnimationData(),
			$skin->getCapeId(),
			$skin->getFullSkinId(),
			$skin->getArmSize(),
			$skin->getSkinColor(),
			$skin->getPersonaPieces(),
			$skin->getPieceTintColors(),
			$skin->isTrusted(),
			$skin->isPremium(),
			$skin->isPersona(),
			$skin->isPersonaCapeOnClassic(),
			$skin->isPrimaryUser(),
			$skin->isOverride()
		);
	}

	public function fromSkinData(SkinData $data) : Skin{
		$skin = new Skin(
			$data->getSkinId(),
			$data->getSkinImage()->getData(),
			$data->getCapeImage()->getData(),
			self::extractGeometryName($data->getResourcePatch(), $data->getArmSize()),
			$data->getGeometryData()
		);
		$skin->setSkinImageDimensions($data->getSkinImage()->getWidth(), $data->getSkinImage()->getHeight());
		$skin->setCapeImageDimensions($data->getCapeImage()->getWidth(), $data->getCapeImage()->getHeight());
		$skin->setSkinResourcePatch($data->getResourcePatch());
		$skin->setGeometryDataEngineVersion($data->getGeometryDataEngineVersion());
		$skin->setAnimationData($data->getAnimationData());
		$skin->setCapeId($data->getCapeId());
		$skin->setFullSkinId($data->getFullSkinId());
		$skin->setArmSize($data->getArmSize());
		$skin->setSkinColor($data->getSkinColor());
		$skin->setPlayFabId($data->getPlayFabId());
		$skin->setAnimations($data->getAnimations());
		$skin->setPersonaPieces($data->getPersonaPieces());
		$skin->setPieceTintColors($data->getPieceTintColors());
		$skin->setTrusted($data->isVerified());
		$skin->setPremium($data->isPremium());
		$skin->setPersona($data->isPersona());
		$skin->setPersonaCapeOnClassic($data->isPersonaCapeOnClassic());
		$skin->setPrimaryUser($data->isPrimaryUser());
		$skin->setOverride($data->isOverride());

		return $skin;
	}
}
