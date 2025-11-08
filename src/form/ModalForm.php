<?php

namespace pocketmine\form;

use pocketmine\player\Player;
use pocketmine\form\response\ModalResponse;

class ModalForm extends IForm {
    /** @var string */
    protected string $content = "";
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

    public function yesText(string $text): self {
        $this->buttons[0] = ['text' => $text];
        return $this;
    }

    public function noText(string $text): self {
        $this->buttons[1] = ['text' => $text];
        return $this;
    }

    protected function serializeFormData(): array {
        return [
            'type' => 'modal',
            'title' => $this->title,
            'content' => $this->content,
            'button1' => $this->buttons[0]['text'] ?? "",
            'button2' => $this->buttons[1]['text'] ?? ""
        ];
    }

    protected function processResponse($data): ModalResponse {
        if($data === null) {
            return new ModalResponse();
        }

        try {
            $index = $data ? 0 : 1;
            if(!isset($this->buttons[$index])) {
                return new ModalResponse();
            }

            $button = $this->buttons[$index];

            return new ModalResponse(
                $index,
                $button['text']
            );

        } catch(\Exception) {
            return new ModalResponse();
        }
    }

    protected function handleButtonCallback(Player $player, ModalResponse $response): void {
        if($this->submitCallback !== null) {
            ($this->submitCallback)($player, $response);
        }
    }
}
