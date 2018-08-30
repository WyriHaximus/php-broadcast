<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\TaskInterface;
use Psr\EventDispatcher\TaskProcessorInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

final class AsyncProcessor implements TaskProcessorInterface
{
    /** @var ListenerProviderInterface */
    private $listeners;

    public function __construct(ListenerProviderInterface $listeners)
    {
        $this->listeners = $listeners;
    }

    public function process(TaskInterface $event): TaskInterface
    {
        if (!($event instanceof ListenerPromisedTask)) {
            return (new Processor($this->listeners))->process($event);
        }

        return new PromisedTask(new Promise(function ($resolve, $reject) use ($event) {
            $listeners = iterator_to_array($this->listeners->getListenersForEvent($event));
            return $this->call($event, $listeners)->done($resolve, $reject);
        }));
    }

    private function call(ListenerPromisedTask $event, array $listeners): PromiseInterface
    {
        if (\count($listeners) === 0) {
            return resolve($event);
        }

        $listener = array_shift($listeners);
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
