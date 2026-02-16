<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Dummy;

use WyriHaximus\Broadcast\Contracts\AsyncListener as AsyncListenerContract;

use function React\Async\await;
use function React\Promise\resolve;

/** @internal */
final class AsyncListener implements AsyncListenerContract
{
    /** @var callable $handler */
    private $handler;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function handle(Event $event): void
    {
        await(resolve(true));
        ($this->handler)($event);
    }

    public static function handleStatic(Event $event): void
    {
        await(resolve(true));
    }
}
