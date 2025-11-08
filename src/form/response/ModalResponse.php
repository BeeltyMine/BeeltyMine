<?php

namespace pocketmine\form\response;

class ModalResponse extends Response {
    /** @var int|null */
    private ?int $index;
    /** @var string|null */
    private ?string $text;
    /** @var bool|null */
    private ?bool $isYes;

    public function __construct(?int $index = null, ?string $text = null) {
        $this->index = $index;
        $this->text = $text;
        $this->isYes = $index === 0 ? true : ($index === 1 ? false : null);
    }

    public function isValid(): bool {
        return $this->index !== null && $this->text !== null;
    }

    public function getIndex(): ?int {
        return $this->index;
    }

    public function getText(): ?string {
        return $this->text;
    }

    public function isYes(): bool {
        return $this->isYes === true;
    }

    public function yes(): bool {
        return $this->isYes();
    }

    public function isNo(): bool {
        return $this->isYes === false;
    }

    public function no(): bool {
        return $this->isNo();
    }
}
