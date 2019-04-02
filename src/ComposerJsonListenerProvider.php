<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use function WyriHaximus\from_get_in_packages_composer;
use function WyriHaximus\iteratorOrArrayToArray;
use function WyriHaximus\listClassesInFile;

final class ComposerJsonListenerProvider implements ListenerProviderInterface
{
    private $events = [];

    /** @var ContainerInterface */
    private $container;

    public function __construct(string $path, ContainerInterface $container)
    {
        $this->container = $container;
        $this->events = iteratorOrArrayToArray($this->locateEvents(from_get_in_packages_composer($path)));
    }

    public function getListenersForEvent(object $event): iterable
    {
        $eventName = \get_class($event);

        if (!isset($this->events[$eventName])) {
            yield from [];
        }

        foreach ($this->events[$eventName] as $listener) {
            yield $this->container->get($listener);
        }
    }

    private function locateEvents(iterable $events): iterable
    {
        foreach ($events as $event => $listeners) {
            yield $event => $this->locateListeners($listeners);
        }
    }

    private function locateListeners(iterable $paths): iterable
    {
        foreach ($paths as $listenerPaths) {
            yield from $this->listListenersInLocation($listenerPaths);
        }
    }

    private function listListenersInLocation(string $location): iterable
    {
        if (\strpos($location, '*') !== false) {
            yield from $this->locateListeners(\glob($location));

            return;
        }

        yield from listClassesInFile($location);
    }
}
