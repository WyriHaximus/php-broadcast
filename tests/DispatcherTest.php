<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Pimple\Container;
use Pimple\Psr11\Container as PsrContainer;
use Psr\Log\LoggerInterface;
use TheOrville\Exceptions\HappyArborDayException;
use TheOrville\Exceptions\LatchcombException;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\Broadcast\ArrayListenerProvider;
use WyriHaximus\Broadcast\ContainerListenerProvider;
use WyriHaximus\Broadcast\Dispatcher;
use WyriHaximus\Broadcast\Dummy\AsyncListener;
use WyriHaximus\Broadcast\Dummy\Event;
use WyriHaximus\Broadcast\Dummy\Listener;

use function React\Async\await;
use function React\Promise\Timer\sleep;

final class DispatcherTest extends AsyncTestCase
{
    #[Test]
    public function messageNoErrors(): void
    {
        $container                       = new Container();
        $flip                            = new Flip();
        $asyncFlip                       = new Flip();
        $message                         = new Event();
        $container[Listener::class]      = new Listener($flip);
        $container[AsyncListener::class] = new AsyncListener($asyncFlip);
        $listenerProvider                = new ContainerListenerProvider(new PsrContainer($container));

        Dispatcher::createFromListenerProvider($listenerProvider)->dispatch($message);

        await(sleep(0.001));

        self::assertTrue($flip->flip());
        self::assertTrue($asyncFlip->flip());
    }

    #[Test]
    public function messageErrorOnFirstSecondStillRunsNoErrorHandler(): void
    {
        $exception = new LatchcombException();
        self::expectException($exception::class);
        $throw = static function () use ($exception): never {
            throw $exception;
        };

        $flip             = new Flip();
        $message          = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [
                $throw,
                $flip,
            ],
        ]);

        Dispatcher::createFromListenerProvider($listenerProvider)->dispatch($message);

        self::assertTrue($flip->flip());
    }

    #[Test]
    public function messageOnErrorLogs(): void
    {
        $exception = new HappyArborDayException();
        self::expectException($exception::class);
        $throw = static function () use ($exception): never {
            throw $exception;
        };

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->with('Unhandled throwable caught: ' . $exception::class, [
            'exception' => (string) $exception,
        ]);
        $message          = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [$throw],
        ]);

        new Dispatcher($listenerProvider, $logger)->dispatch($message);
    }
}
