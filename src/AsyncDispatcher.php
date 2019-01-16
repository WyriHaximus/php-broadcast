<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use function WyriHaximus\iteratorOrArrayToArray;

final class AsyncDispatcher implements EventDispatcherInterface
{
    /** @var ListenerProviderInterface */
    private $listeners;

    public function __construct(ListenerProviderInterface $listeners)
    {
        $this->listeners = $listeners;
    }

    /**
     * @param  object                   $event
     * @return PromiseInterface<object>
     */
    public function dispatch(object $event)
    {
        if (!($event instanceof ListenerPromisedTask)) {
            return (new Dispatcher($this->listeners))->process($event);
        }

        $listeners = iteratorOrArrayToArray($this->listeners->getListenersForEvent($event));

        return $this->call($event, $listeners);
    }

    private function call(ListenerPromisedTask $event, array $listeners): PromiseInterface
    {
        if (\count($listeners) === 0) {
            return resolve($event);
        }

        $listener = \array_shift($listeners);
        $listener($event);

        $promise = $event->getFor($listener);
        if ($promise === null) {
            return $this->call($event, $listeners);
        }

        return $promise->then(function () use ($event, $listeners) {
            return $this->call($event, $listeners);
        });
    }
}
