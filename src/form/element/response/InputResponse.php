<?php

namespace pocketmine\form\element\response;

class InputResponse extends ElementResponse {
    private string $value;

    public function __construct(string $elementId, string $elementText, string $value) {
        parent::__construct($elementId, $elementText, 'input');
        $this->value = $value;
    }

    public function getText(): string {
        return $this->value;
    }
}