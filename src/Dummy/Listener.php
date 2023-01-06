<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Dummy;

use RuntimeException;
use stdClass;
use WyriHaximus\Broadcast\Contracts\DoNotHandle;
use WyriHaximus\Broadcast\Contracts\Listener as ListenerContract;

/**
 * @internal
 */
final class Listener implements ListenerContract
{
    /** @var callable $handler */
    private $handler;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function handle(Event $event): void
    {
        ($this->handler)($event);
    }

    public function handleBoth(Event|stdClass $event): void
    {
        ($this->handler)($event);
    }

    #[DoNotHandle]
    public function doNotHandle(Event $event): void
    {
        throw new RuntimeException('Should not be called');
    }
}
