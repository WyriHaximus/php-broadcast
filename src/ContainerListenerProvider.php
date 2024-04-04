<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use WyriHaximus\Broadcast\Generated\AbstractListenerProvider;

use function func_get_args;
use function React\Async\async;
use function React\Async\await;

/**
 * This extended AbstractListenerProvider class is generated by wyrihaximus/broadcast and inspired by bmack/kart-composer-plugin
 */
final class ContainerListenerProvider extends AbstractListenerProvider implements ListenerProviderInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /** @inheritDoc */
    protected function prepareCallable(array $listeners): iterable
    {
        foreach ($listeners as $listener) {
            if ($listener['static']) {
                /**
                 * @psalm-suppress InvalidArgument
                 * @phpstan-ignore-next-line
                 */
                yield $this->wrapIfAsync($listener['class'] . '::' . $listener['method'], $listener);

                continue;
            }

            /** @phpstan-ignore-next-line */
            yield $this->wrapIfAsync([$this->container->get($listener['class']), $listener['method']], $listener);
        }
    }

    /** @param array{class: string, method: string, static: bool, async: bool} $listener */
    private function wrapIfAsync(callable $callable, array $listener): callable
    {
        if ($listener['async']) {
            return static fn (): mixed => await(async(static fn (): mixed => $callable(...func_get_args()))(...func_get_args()));
        }

        return $callable;
    }
}
