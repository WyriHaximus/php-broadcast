<?php declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use ApiClients\Tools\TestUtilities\TestCase;
use Psr\Log\LoggerInterface;
use TheOrville\Exceptions\HappyArborDayException;
use TheOrville\Exceptions\LatchcombException;
use WyriHaximus\Broadcast\ArrayListenerProvider;
use WyriHaximus\Broadcast\Notifier;

/**
 * @internal
 */
final class NotifierTest extends TestCase
{
    public function testMessageNoErrors(): void
    {
        $flip = false;
        $message = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [
                function () use (&$flip): void {
                    $flip = true;
                },
            ],
        ]);

        (new Notifier($listenerProvider))->notify($message);

        self::assertTrue($flip);
    }

    public function testMessageErrorOnFirstSecondStillRunsNoErrorHandler(): void
    {
        $flip = false;
        $message = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [
                function (): void {
                    throw new LatchcombException();
                },
                function () use (&$flip): void {
                    $flip = true;
                },
            ],
        ]);

        (new Notifier($listenerProvider))->notify($message);

        self::assertTrue($flip);
    }

    public function testMessageOnErrorLogs(): void
    {
        $exception = new HappyArborDayException();
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error((string)$exception)->shouldBeCalled();
        $message = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [
                function () use ($exception): void {
                    throw $exception;
                },
            ],
        ]);

        (new Notifier($listenerProvider, $logger->reveal()))->notify($message);
    }
}
