<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\TaskInterface;
use React\Promise\PromiseInterface;

final class ListenerPromisedTask extends Task
{
    /** @var PromiseInterface[] */
    private $promises;

    public function promise(object $object, PromiseInterface $promise): void
    {
        $hash = spl_object_hash($object);
        $this->promises[$hash] = $promise;
    }

    public function getFor(object $object): ?PromiseInterface
    {
        $hash = spl_object_hash($object);
        return $this->promises[$hash] ?? null;
    }
}
