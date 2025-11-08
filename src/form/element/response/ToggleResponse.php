<?php

namespace pocketmine\form\element\response;

class ToggleResponse extends ElementResponse {
    private bool $value;

    public function __construct(string $elementId, string $elementText, bool $value) {
        parent::__construct($elementId, $elementText, 'toggle');
        $this->value = $value;
    }

    public function getValue(): bool {
        return $this->value;
    }
}