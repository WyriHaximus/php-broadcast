<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Dummy;

use RuntimeException;
use stdClass;
use WyriHaximus\Broadcast\Contracts\DoNotHandle;
use WyriHaximus\Broadcast\Contracts\Listener as ListenerContract;

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

    public function thisShouldNotBeDetected(string $event): void
    {
        ($this->handler)($event);
    }

    public function handleBoth(Event|stdClass $event): void
    {
        ($this->handler)($event);
    }

    public static function handleBothStaticly(Event|stdClass $event): void
    {
    }

    #[DoNotHandle]
    public function doNotHandle(Event $event): never
    {
        throw new RuntimeException('Should not be called');
    }

    public function doNotHandleDueToTwoArguments(
        Event $event,
        stdClass $std,
    ): never {
        throw new RuntimeException('Should not be called');
    }

    protected function doNotHandleProtected(Event $event): never
    {
        throw new RuntimeException('Should not be called');
    }

    private function doNotHandlePrivate(Event $event): never
    {
        throw new RuntimeException('Should not be called');
    }
}
