<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Broadcast\ArrayListenerProvider;
use WyriHaximus\TestUtilities\TestCase;

#[CoversClass(ArrayListenerProvider::class)]
final class ArrayListenerProviderTest extends TestCase
{
    #[Test]
    public function noListenersForUnknownEvent(): void
    {
        $listenerProvider = new ArrayListenerProvider([]);
        $listeners        = [...$listenerProvider->getListenersForEvent(new TestMessage())];

        self::assertSame([], $listeners);
    }

    #[Test]
    public function yieldsRegisteredListeners(): void
    {
        $flip             = new Flip();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [$flip],
        ]);
        $listeners        = [...$listenerProvider->getListenersForEvent(new TestMessage())];

        self::assertSame([$flip], $listeners);
    }
}
