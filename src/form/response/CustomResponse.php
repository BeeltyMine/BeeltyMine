<?php

namespace pocketmine\form\response;

use pocketmine\form\element\response\DropdownResponse;
use pocketmine\form\element\response\ElementResponse;
use pocketmine\form\element\response\InputResponse;
use pocketmine\form\element\response\SliderResponse;
use pocketmine\form\element\response\ToggleResponse;

class CustomResponse extends Response {
    /** @var ElementResponse[] */
    private array $responses;
    /** @var array */
    private array $responseMap = [];

    public function __construct(array $responses = []) {
        $this->responses = $responses;
        $this->isClosed = empty($responses);

        foreach ($responses as $index => $response) {
            $type = $response->getElementType();
            $this->responseMap[$type][$index] = $response;
        }
    }

    public function isValid() : bool
    {
        return !$this->isClosed;
    }

    public function getResponses(): array {
        return $this->responses;
    }

    public function getResponse(int $index): ?ElementResponse {
        return $this->responses[$index] ?? null;
    }


    /**
     * @return InputResponse[]
     */
    public function getInputResponses(): array {
        return $this->responseMap['input'] ?? [];
    }

    public function getInputResponse(int $index): ?InputResponse {
        $res = $this->getResponse($index);
        return $res instanceof InputResponse ? $res : null;
    }

    /**
     * @return ToggleResponse[]
     */
    public function getToggleResponses(): array {
        return $this->responseMap['toggle'] ?? [];
    }

    public function getToggleResponse(int $index): ?ToggleResponse {
        $res = $this->getResponse($index);
        return $res instanceof ToggleResponse ? $res : null;
    }

    /**
     * @return SliderResponse[]
     */
    public function getSliderResponses(): array {
        return $this->responseMap['slider'] ?? [];
    }

    public function getSliderResponse(int $index): ?SliderResponse {
        $res = $this->getResponse($index);
        return $res instanceof SliderResponse ? $res : null;
    }

    /**
     * @return DropdownResponse[]
     */
    public function getDropdownResponses(): array {
        return $this->responseMap['dropdown'] ?? [];
    }

    public function getDropdownResponse(int $index): ?DropdownResponse {
        $res = $this->getResponse($index);
        return $res instanceof DropdownResponse ? $res : null;
    }
}