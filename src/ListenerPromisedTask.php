<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\TaskInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

final class ListenerPromisedTask extends Task
{
    /** @var PromiseInterface[] */
    private $promises;

    /** @var Deferred */
    private $deferred;

    public function promise(object $object, PromiseInterface $promise): void
    {
        $hash = spl_object_hash($object);
        $this->promises[$hash] = $promise;
    }

    public function getFor(object $object): ?PromiseInterface
    {
        $hash = spl_object_hash($object);
        return $this->promises[$object] ?? null;
    }

    public function then($resolve, $reject): PromiseInterface
    {
        if ($this->deferred === null) {
            $this->deferred = new Deferred();
        }

        return $this->deferred->promise()->then($resolve, $reject);
    }

    public function resolve($value): void
    {
        if ($this->deferred === null) {
            $this->deferred = new Deferred();
        }

        $this->deferred->resolve($value);
    }

    public function reject($value): void
    {
        if ($this->deferred === null) {
            $this->deferred = new Deferred();
        }

        $this->deferred->reject($value);
    }
}
