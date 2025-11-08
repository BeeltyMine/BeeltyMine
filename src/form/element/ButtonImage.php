<?php

namespace pocketmine\form\element;

class ButtonImage {
    public const IMAGE_TYPE_PATH = "path";
    public const IMAGE_TYPE_URL = "url";

    /** @var string */
    private $type;
    /** @var string */
    private $data;

    /**
     * @param string $type
     * @param string $data
     */
    public function __construct(string $type, string $data) {
        $this->type = $type;
        $this->data = $data;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getData(): string {
        return $this->data;
    }

    public static function path(string $path): self {
        return new self(self::IMAGE_TYPE_PATH, $path);
    }

    public static function url(string $url): self {
        return new self(self::IMAGE_TYPE_URL, $url);
    }

    public function jsonSerialize(): array {
        return [
            "type" => $this->type,
            "data" => $this->data
        ];
    }
}