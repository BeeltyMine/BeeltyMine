<?php
namespace pocketmine\form\response;

abstract class Response {
    protected bool $isClosed = false;

    public function isClosed(): bool {
        return $this->isClosed;
    }
}