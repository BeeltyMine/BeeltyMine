<?php

namespace pocketmine\form;

use pocketmine\form\response\ModalResponse;
use pocketmine\form\response\Response;
use pocketmine\player\Player;

abstract class IForm implements Form {
    /** @var string */
    protected string $title;
    /** @var callable|null */
    protected $submitCallback;
    /** @var callable|null */
    protected $closeCallback;

    public function __construct(string $title = "") {
        $this->title = $title;
    }

    public function onSubmit(callable $callback): self {
        $this->submitCallback = $callback;
        return $this;
    }

    public function onClose(callable $callback): self {
        $this->closeCallback = $callback;
        return $this;
    }

    public function send(Player $player): void {
        $player->sendForm($this);
    }

    public function sendToPlayer(Player $player): void {
        $player->sendForm($this);
    }

    public function handleResponse(Player $player, $data): void {
        if($data === null) {
            if($this->closeCallback !== null) {
                ($this->closeCallback)($player);
            }
            return;
        }

        $response = $this->processResponse($data);

        if($response === null || !$response->isValid()) {
            if($this->closeCallback !== null) {
                ($this->closeCallback)($player);
            }
            return;
        }

        if(method_exists($this, 'handleButtonCallback') && $response instanceof ModalResponse) {
            $this->handleButtonCallback($player, $response);
            return;
        }

        if($this->submitCallback !== null) {
            ($this->submitCallback)($player, $response);
        }
    }

    public function jsonSerialize(): array {
        return $this->serializeFormData();
    }

    abstract protected function serializeFormData(): array;
    abstract protected function processResponse($data): ?Response;
}