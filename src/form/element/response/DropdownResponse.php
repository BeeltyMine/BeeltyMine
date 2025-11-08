<?php

namespace pocketmine\form\element\response;

class DropdownResponse extends ElementResponse {
    private string $selectedOption;
    private int $selectedIndex;
    private array $options;

    public function __construct(string $elementId, string $elementText, int $selectedIndex, string $selectedOption, array $options) {
        parent::__construct($elementId, $elementText, 'dropdown');
        $this->selectedIndex = $selectedIndex;
        $this->selectedOption = $selectedOption;
        $this->options = $options;
    }

    public function getSelectedOption(): string {
        return $this->selectedOption;
    }

    public function getSelectedIndex(): int {
        return $this->selectedIndex;
    }

    public function getOptions(): array {
        return $this->options;
    }
}