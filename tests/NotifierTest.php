<?php declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use ApiClients\Tools\TestUtilities\TestCase;
use Psr\Log\LoggerInterface;
use TheOrville\Exceptions\HappyArborDayException;
use TheOrville\Exceptions\LatchcombException;
use WyriHaximus\Broadcast\ArrayListenerProvider;
use WyriHaximus\Broadcast\Notifier;

final class NotifierTest extends TestCase
{
    public function testMessageNoErrors()
    {
        $flip = false;
        $message = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [
                function () use (&$flip) {
                    $flip = true;
                },
            ],
        ]);

        (new Notifier($listenerProvider))->notify($message);

        self::assertTrue($flip);
    }

    public function testMessageErrorOnFirstSecondStillRunsNoErrorHandler()
    {
        $flip = false;
        $message = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [
                function () {
                    throw new LatchcombException();
                },
                function () use (&$flip) {
                    $flip = true;
                },
            ],
        ]);

        (new Notifier($listenerProvider))->notify($message);

        self::assertTrue($flip);
    }

    public function testMessageOnErrorLogs()
    {
        $exception = new HappyArborDayException();
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error((string)$exception)->shouldBeCalled();
        $message = new TestMessage();
        $listenerProvider = new ArrayListenerProvider([
            TestMessage::class => [
                function () use ($exception) {
                    throw $exception;
                },
            ],
        ]);

        (new Notifier($listenerProvider, $logger->reveal()))->notify($message);
    }
}
