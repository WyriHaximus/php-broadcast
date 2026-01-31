<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function assert;
use function is_callable;

/** @api */
final readonly class Dispatcher implements EventDispatcherInterface
{
    public static function createFromListenerProvider(ListenerProviderInterface $listenerProvider): self
    {
        return new self($listenerProvider, new NullLogger());
    }

    public function __construct(private ListenerProviderInterface $listenerProvider, private LoggerInterface $logger)
    {
    }

    /**
     * @param T $event
     *
     * @return T
     *
     * @template T of object
     */
    public function dispatch(object $event): object
    {
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            assert(is_callable($listener));
            try {
                $listener($event);
            } catch (Throwable $throwable) {
                $this->logger->error('Unhandled throwable caught: ' . $throwable::class, ['exception' => $throwable]);

                throw $throwable;
            }
        }

        return $event;
    }
}
