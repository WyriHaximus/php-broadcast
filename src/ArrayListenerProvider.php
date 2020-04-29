<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\ListenerProviderInterface;
use function array_key_exists;
use function get_class;

final class ArrayListenerProvider implements ListenerProviderInterface
{
    /** @var array<string, array<int, callable>> */
    private array $events = [];

    /**
     * @param array<string, array<int, callable>> $events
     */
    public function __construct(array $events)
    {
        $this->events = $events;
    }

    /**
     * @return iterable<int, callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventName = get_class($event);

        if (array_key_exists($eventName, $this->events)) {
            yield from $this->events[$eventName];
        }

        yield from [];
    }
}
