<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

final class Dispatcher implements EventDispatcherInterface
{
    /**
     * @var ListenerProviderInterface
     */
    private $listeners;

    /**
     * @param ListenerProviderInterface $listeners
     */
    public function __construct(ListenerProviderInterface $listeners)
    {
        $this->listeners = $listeners;
    }

    public function dispatch(object $event)
    {
        foreach ($this->listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }

        return $event;
    }
}
