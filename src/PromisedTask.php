<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\EventDispatcher\TaskInterface;
use React\Promise\PromiseInterface;

final class PromisedTask extends Task implements PromiseInterface
{
    /** @var PromiseInterface */
    private $promise;

    /**
     * @param PromiseInterface $promise
     */
    public function __construct(PromiseInterface $promise)
    {
        $this->promise = $promise;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        return $this->promise->then($onFulfilled, $onRejected, $onProgress);
    }
}
