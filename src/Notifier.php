<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\MessageInterface;
use Psr\EventDispatcher\MessageNotifierInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class Notifier implements MessageNotifierInterface
{
    /**
     * @var ListenerProviderInterface
     */
    private $listeners;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ListenerProviderInterface $listeners
     * @param LoggerInterface           $logger
     */
    public function __construct(ListenerProviderInterface $listeners, LoggerInterface $logger = null)
    {
        $this->listeners = $listeners;
        $this->logger = $logger ?? new NullLogger();
    }

    public function notify(MessageInterface $event): void
    {
        foreach ($this->listeners->getListenersForEvent($event) as $listener) {
            try {
                $listener($event);
            } catch (Throwable $et) {
                $this->logger->error((string)$et);
            }
        }
    }
}
