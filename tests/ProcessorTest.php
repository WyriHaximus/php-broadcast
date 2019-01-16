<?php declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use ApiClients\Tools\TestUtilities\TestCase;
use TheOrville\Exceptions\HappyArborDayException;
use WyriHaximus\Broadcast\ArrayListenerProvider;
use WyriHaximus\Broadcast\Dispatcher;

/**
 * @internal
 */
final class ProcessorTest extends TestCase
{
    public function testNoErrorDuringProcess(): void
    {
        $flip = false;
        $listenerProvider = new ArrayListenerProvider([
            TestTask::class => [
                function () use (&$flip): void {
                    $flip = true;
                },
            ],
        ]);

        (new Dispatcher($listenerProvider))->process(new TestTask());

        self::assertTrue($flip);
    }

    public function testErrorDuringProcess(): void
    {
        $this->expectException(HappyArborDayException::class);
        $this->expectExceptionMessage('wood');

        $listenerProvider = new ArrayListenerProvider([
            TestTask::class => [
                function (): void {
                    throw new HappyArborDayException('wood');
                },
            ],
        ]);

        (new Dispatcher($listenerProvider))->process(new TestTask());
    }

    public function testProcessReturnsTheSameInstanceItWasPassed(): void
    {
        $task = new TestTask();
        $listenerProvider = new ArrayListenerProvider([
            TestTask::class => [
                function (TestTask $task): void {
                    $task->flip = true;
                },
            ],
        ]);

        self::assertFalse($task->flip);

        $returnedTask = (new Dispatcher($listenerProvider))->process($task);

        self::assertTrue($task->flip);
        self::assertSame($task, $returnedTask);
    }
}
