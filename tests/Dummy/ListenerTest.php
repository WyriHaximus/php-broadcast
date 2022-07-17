<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast\Dummy;

use RuntimeException;
use Throwable;
use WyriHaximus\Broadcast\Dummy\Event;
use WyriHaximus\Broadcast\Dummy\Listener;
use WyriHaximus\TestUtilities\TestCase;

final class ListenerTest extends TestCase
{
    /**
     * @test
     */
    public function doNotHandle(): void
    {
        self::expectException(Throwable::class);
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Should not be called');

        (new Listener(static fn (): bool => false))->doNotHandle(new Event());
    }
}
