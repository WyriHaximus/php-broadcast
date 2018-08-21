<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\TaskInterface;
use Psr\EventDispatcher\TaskProcessorInterface;

final class Processor implements TaskProcessorInterface
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

    public function process(TaskInterface $event): TaskInterface
    {
        foreach ($this->listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }

        return $event;
    }
}
