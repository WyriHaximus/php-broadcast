<?php declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use Psr\Log\LoggerInterface;
use TheOrville\Exceptions\HappyArborDayException;
use TheOrville\Exceptions\LatchcombException;
use WyriHaximus\Broadcast\ArrayListenerProvider;
use WyriHaximus\Broadcast\Dispatcher;
use WyriHaximus\TestUtilities\TestCase;
use function get_class;

/**
 * @internal
 */
final class DispatcherTest extends TestCase
{
    public function testMessageNoErrors(): void
    {
        $flip             = new Flip();
        $message          = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [$flip],
        ]);

        Dispatcher::createFromListenerProvider($listenerProvider)->dispatch($message);

        self::assertTrue($flip->flip());
    }

    public function testMessageErrorOnFirstSecondStillRunsNoErrorHandler(): void
    {
        $throw            = static function (): void {
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
        $logger->error('Unhandled throwable caught: ' . get_class($exception), [
            'exception' => (string) $exception,
        ])->shouldBeCalled();
        $message          = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [$throw],
        ]);

        (new Dispatcher($listenerProvider, $logger->reveal()))->dispatch($message);
    }
}
