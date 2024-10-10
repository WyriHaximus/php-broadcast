<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use JsonSerializable;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class Item implements ItemContract, JsonSerializable
{
    /** @param class-string $class */
    public function __construct(
        public string $event,
        public string $class,
        public string $method,
        public bool $static,
        public bool $async,
    ) {
    }

    /** @return array{event: string, class: class-string, method: string, static: bool, async: bool} */
    public function jsonSerialize(): array
    {
        return [
            'event' => $this->event,
            'class' => $this->class,
            'method' => $this->method,
            'static' => $this->static,
            'async' => $this->async,
        ];
    }
}
