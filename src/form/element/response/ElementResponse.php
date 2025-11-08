<?php

namespace pocketmine\form\element\response;

abstract class ElementResponse {
    protected string $elementId;
    protected string $elementText;
    protected string $elementType;

    public function __construct(string $elementId, string $elementText, string $elementType) {
        $this->elementId = $elementId;
        $this->elementText = $elementText;
        $this->elementType = $elementType;
    }

    public function getElementId(): string {
        return $this->elementId;
    }

    public function getElementText(): string {
        return $this->elementText;
    }

    public function getElementType(): string {
        return $this->elementType;
    }
}