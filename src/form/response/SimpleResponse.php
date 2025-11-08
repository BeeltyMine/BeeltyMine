<?php

namespace pocketmine\form\response;

class SimpleResponse extends Response {
    /** @var int */
    private int $buttonId;
    /** @var string|null */
    private ?string $buttonText;
    /** @var array|null */
    private ?array $buttonImage;
    /** @var mixed|null */
    private mixed $customData;

    public function __construct(int $buttonId = -1, ?string $buttonText = null, ?array $buttonImage = null, mixed $customData = null) {
        $this->buttonId = $buttonId;
        $this->buttonText = $buttonText;
        $this->buttonImage = $buttonImage;
        $this->customData = $customData;
    }

    public function getButtonId(): int {
        return $this->buttonId;
    }

    public function getButtonText(): ?string {
        return $this->buttonText;
    }

    public function getButtonImage(): ?array {
        return $this->buttonImage;
    }

    public function getCustomData() : mixed {
        return $this->customData;
    }

    public function isValid(): bool {
        return $this->buttonId !== -1 && $this->buttonText !== null;
    }

    public function hasImage(): bool {
        return $this->buttonImage !== null;
    }

    public function getImageType(): ?string {
        return $this->buttonImage['type'] ?? null;
    }

    public function getImagePath(): ?string {
        return $this->buttonImage['data'] ?? null;
    }
}