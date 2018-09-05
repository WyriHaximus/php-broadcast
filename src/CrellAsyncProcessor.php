<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\TaskInterface;
use Psr\EventDispatcher\TaskProcessorInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

final class CrellAsyncProcessor implements TaskProcessorInterface
{
    /** @var TaskProcessorInterface */
    private $processor;

    public function __construct(ListenerProviderInterface $listeners)
    {
        $this->processor = new Processor($listeners);
    }

    public function process(TaskInterface $event): TaskInterface
    {
        if (!($event instanceof PromisedTask)) {
            return $this->processor->process($event);
        }

        return new PromisedTask(new Promise(function ($resolve, $reject) use ($event) {
            resolve($event)->then(function (TaskInterface $event) {
                return $this->processor->process($event);
            })->done($resolve, $reject);
        }));
    }
}
