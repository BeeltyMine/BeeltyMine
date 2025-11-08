<?php

namespace pocketmine\form\element\response;

class SliderResponse extends ElementResponse {
    private int $value;

    public function __construct(string $elementId, string $elementText, int $value) {
        parent::__construct($elementId, $elementText, 'slider');
        $this->value = $value;
    }

    public function getValue(): int {
        return $this->value;
    }
}