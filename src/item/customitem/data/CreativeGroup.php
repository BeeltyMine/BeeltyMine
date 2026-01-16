<?php

declare(strict_types=1);

namespace pocketmine\item\customitem\data;

/**
 * Represents a creative inventory group for custom items.
 */
final class CreativeGroup{
	
	public function __construct(
		private string $id,
		private string $displayName
	){}
	
	public function getId() : string{
		return $this->id;
	}
	
	public function getDisplayName() : string{
		return $this->displayName;
	}
}
