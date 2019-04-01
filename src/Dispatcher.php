<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class Dispatcher implements EventDispatcherInterface
{
    /** @var ListenerProviderInterface */
    private $listenerProvider;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param ListenerProviderInterface $listenerProvider
     * @param LoggerInterface           $logger
     */
    public function __construct(ListenerProviderInterface $listenerProvider, ?LoggerInterface $logger = null)
    {
        $this->listenerProvider = $listenerProvider;
        $this->logger = $logger ?? new NullLogger();
    }

    public function dispatch(object $event): void
    {
        /** @var callable $listener */
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            try {
                $listener($event);
            } catch (Throwable $throwable) {
                $this->logger->error('Unhandled throwable caught: ' . \get_class($throwable), [
                    'exception' => (string)$throwable,
                ]);
            }
        }
    }
}
