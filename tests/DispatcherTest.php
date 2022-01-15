<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use Pimple\Container;
use Pimple\Psr11\Container as PsrContainer;
use Psr\Log\LoggerInterface;
use TheOrville\Exceptions\HappyArborDayException;
use TheOrville\Exceptions\LatchcombException;
use WyriHaximus\Broadcast\ArrayListenerProvider;
use WyriHaximus\Broadcast\ContainerListenerProvider;
use WyriHaximus\Broadcast\Dispatcher;
use WyriHaximus\Broadcast\Dummy\Event;
use WyriHaximus\Broadcast\Dummy\Listener;
use WyriHaximus\TestUtilities\TestCase;

final class DispatcherTest extends TestCase
{
    public function testMessageNoErrors(): void
    {
        $container                  = new Container();
        $flip                       = new Flip();
        $message                    = new Event();
        $container[Listener::class] = new Listener($flip);
        $listenerProvider           = new ContainerListenerProvider(new PsrContainer($container));

        Dispatcher::createFromListenerProvider($listenerProvider)->dispatch($message);

        self::assertTrue($flip->flip());
    }

    public function testMessageErrorOnFirstSecondStillRunsNoErrorHandler(): void
    {
        $throw = static function (): void {
            throw new LatchcombException();
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

    public function testMessageOnErrorLogs(): void
    {
        $exception = new HappyArborDayException();
        $throw     = static function () use ($exception): void {
            throw $exception;
        };
        $logger    = $this->prophesize(LoggerInterface::class);
        $logger->error('Unhandled throwable caught: ' . $exception::class, [
            'exception' => (string) $exception,
        ])->shouldBeCalled();
        $message          = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [$throw],
        ]);

        (new Dispatcher($listenerProvider, $logger->reveal()))->dispatch($message);
    }
}
