<?php

namespace pocketmine\form;

use pocketmine\form\element\response\DropdownResponse;
use pocketmine\form\element\response\InputResponse;
use pocketmine\form\element\response\SliderResponse;
use pocketmine\form\element\response\ToggleResponse;
use pocketmine\form\response\CustomResponse;

class CustomForm extends IForm {
    /** @var array */
    protected array $elements = [];
    /** @var array */
    protected array $content = [];
    /** @var int */
    private int $currentId = 0;

    public function __construct(string $title = "") {
        parent::__construct($title);
    }

    public function title(string $title) : self {
        $this->title = $title;
        return $this;
    }


    public function header(string $text): self {
        $this->elements[] = [
            'type' => 'header',
            'text' => $text
        ];
        return $this;
    }

    public function label(string $text): self {
        $this->elements[] = [
            'type' => 'label',
            'text' => $text
        ];
        return $this;
    }

    public function divider(string $text = ""): self {
        $this->elements[] = [
            'type' => 'divider',
            'text' => $text
        ];
        return $this;
    }

    public function input(string $text, string $placeholder = "", string $default = ""): self {
        $this->content[] = [
            'type' => 'input',
            'text' => $text,
            'placeholder' => $placeholder,
            'default' => $default
        ];
        return $this;
    }

    public function toggle(string $text, bool $default = false): self {
        $this->content[] = [
            'type' => 'toggle',
            'text' => $text,
            'default' => $default
        ];
        return $this;
    }

    public function slider(string $text, int $min, int $max, int $step = 1, int $default = 0): self {
        $this->content[] = [
            'type' => 'slider',
            'text' => $text,
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'default' => $default
        ];
        return $this;
    }

    public function dropdown(string $text, array $options, int $default = 0): self {
        $this->content[] = [
            'type' => 'dropdown',
            'text' => $text,
            'options' => $options,
            'default' => $default
        ];
        return $this;
    }

    protected function serializeFormData(): array {
        return [
            'type' => 'custom_form',
            'title' => $this->title,
            'elements' => $this->elements,
            'content' => $this->content
        ];
    }


    protected function processResponse($data): CustomResponse {
        if ($data === null) {
            return new CustomResponse();
        }

        if (!is_array($data)) {
            return new CustomResponse();
        }

        $responses = [];
        foreach ($this->content as $index => $element) {
            if (!isset($data[$index])) {
                continue;
            }

            $elementId = 'element_' . $this->currentId++;
            $response = null;

            switch ($element['type']) {
                case 'input':
                    $response = new InputResponse(
                        $elementId,
                        $element['text'],
                        (string)$data[$index]
                    );
                    break;

                case 'toggle':
                    $response = new ToggleResponse(
                        $elementId,
                        $element['text'],
                        (bool)$data[$index]
                    );
                    break;

                case 'slider':
                    $response = new SliderResponse(
                        $elementId,
                        $element['text'],
                        (int)$data[$index]
                    );
                    break;

                case 'dropdown':
                    $selectedIndex = (int)$data[$index];
                    $selectedOption = $element['options'][$selectedIndex] ?? '';
                    $response = new DropdownResponse(
                        $elementId,
                        $element['text'],
                        $selectedIndex,
                        $selectedOption,
                        $element['options']
                    );
                    break;
            }

            if ($response !== null) {
                $responses[] = $response;
            }
        }

        return new CustomResponse($responses);
    }

    /**
     * @return InputResponse[]
     */
    public function getInputResponses(CustomResponse $response): array {
        return array_filter($response->getResponses(), fn($r) => $r instanceof InputResponse);
    }

    public function getInputResponse(CustomResponse $response, int $index): ?InputResponse {
        $res = $response->getResponse($index);
        return $res instanceof InputResponse ? $res : null;
    }

    /**
     * @return ToggleResponse[]
     */
    public function getToggleResponses(CustomResponse $response): array {
        return array_filter($response->getResponses(), fn($r) => $r instanceof ToggleResponse);
    }

    public function getToggleResponse(CustomResponse $response, int $index): ?ToggleResponse {
        $res = $response->getResponse($index);
        return $res instanceof ToggleResponse ? $res : null;
    }

    /**
     * @return SliderResponse[]
     */
    public function getSliderResponses(CustomResponse $response): array {
        return array_filter($response->getResponses(), fn($r) => $r instanceof SliderResponse);
    }

    public function getSliderResponse(CustomResponse $response, int $index): ?SliderResponse {
        $res = $response->getResponse($index);
        return $res instanceof SliderResponse ? $res : null;
    }

    /**
     * @return DropdownResponse[]
     */
    public function getDropdownResponses(CustomResponse $response): array {
        return array_filter($response->getResponses(), fn($r) => $r instanceof DropdownResponse);
    }

    public function getDropdownResponse(CustomResponse $response, int $index): ?DropdownResponse {
        $res = $response->getResponse($index);
        return $res instanceof DropdownResponse ? $res : null;
    }
}