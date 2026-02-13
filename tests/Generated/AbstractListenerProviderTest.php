<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast\Generated;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Pimple\Container;
use Pimple\Psr11\Container as PsrContainer;
use stdClass;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\Broadcast\ContainerListenerProvider;
use WyriHaximus\Broadcast\Dummy\AsyncListener;
use WyriHaximus\Broadcast\Dummy\Event;
use WyriHaximus\Broadcast\Dummy\Listener;

final class AbstractListenerProviderTest extends AsyncTestCase
{
    /** @return iterable<string, array{object, int}> */
    public static function eventNames(): iterable
    {
        yield Event::class => [new Event(), 5];
        yield stdClass::class => [new stdClass(), 2];
    }

    #[Test]
    #[DataProvider('eventNames')]
    public function eventCounts(object $event, int $expectedCount): void
    {
        $container                       = new Container();
        $container[Listener::class]      = new Listener(static fn (): bool => true);
        $container[AsyncListener::class] = new AsyncListener(static fn (): bool => true);
        $listenerProvider                = new ContainerListenerProvider(new PsrContainer($container));
        $events                          = [...$listenerProvider->getListenersForEvent($event)];

        self::assertCount($expectedCount, $events);
    }
}
