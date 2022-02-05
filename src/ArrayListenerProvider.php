<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\ListenerProviderInterface;

use function array_key_exists;

final class ArrayListenerProvider implements ListenerProviderInterface
{
    /**
     * @param array<string, array<int, callable>> $events
     */
    public function __construct(private array $events)
    {
    }

    /**
     * @return iterable<int, callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventName = $event::class;

        if (array_key_exists($eventName, $this->events)) {
            yield from $this->events[$eventName];
        }

        yield from [];
    }
}
