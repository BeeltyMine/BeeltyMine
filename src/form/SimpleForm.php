<?php

namespace pocketmine\form;

use pocketmine\form\element\ButtonImage;
use pocketmine\form\response\SimpleResponse;

class SimpleForm extends IForm {
    /** @var string */
    protected string $content = "";
    /** @var array */
    protected array $elements = [];
    /** @var array */
    protected array $buttons = [];

    public function __construct(string $title = "", string $content = "") {
        parent::__construct($title);
        $this->content = $content;
    }

    public function title(string $title) : self {
        $this->title = $title;
        return $this;
    }

    public function content(string $content): self {
        $this->content = $content;
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

    public function button(string $text, ?ButtonImage $image = null): self {
        $button = [
            'type' => 'button',
            'text' => $text,
            'image' => $image
        ];
        $this->buttons[] = $button;
        return $this;
    }

    protected function serializeFormData(): array {
        $elements = [];

        foreach($this->buttons as $button) {
            $buttonData = [
                'type' => 'button',
                'text' => $button['text']
            ];

            if($button['image'] !== null) {
                $buttonData['image'] = [
                    'type' => $button['image']->getType(),
                    'data' => $button['image']->getData()
                ];
            }

            $elements[] = $buttonData;

        }

        return [
            'type' => 'form',
            'title' => $this->title,
            'content' => $this->content,
            'elements' => $elements
        ];
    }


    protected function processResponse($data): SimpleResponse {
        if($data === null) return new SimpleResponse();

        try {
            $index = (int)$data;

            if(!isset($this->buttons[$index])) return new SimpleResponse();

            $button = $this->buttons[$index];
            $imageData = null;

            if($button['image'] !== null) {
                $imageData = [
                    'type' => $button['image']->getType(),
                    'data' => $button['image']->getData()
                ];
            }

            return new SimpleResponse(
                $index,
                $button['text'],
                $imageData
            );

        } catch(\Exception) {
            return new SimpleResponse();
        }
    }
}
