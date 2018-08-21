<?php declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use ApiClients\Tools\TestUtilities\TestCase;
use TheOrville\Exceptions\HappyArborDayException;
use WyriHaximus\Broadcast\ArrayListenerProvider;
use WyriHaximus\Broadcast\Processor;

final class ProcessorTest extends TestCase
{
    public function testNoErrorDuringProcess()
    {
        $flip = false;
        $listenerProvider = new ArrayListenerProvider([
            TestTask::class => [
                function () use (&$flip) {
                    $flip = true;
                },
            ],
        ]);

        (new Processor($listenerProvider))->process(new TestTask());

        self::assertTrue($flip);
    }

    public function testErrorDuringProcess()
    {
        $this->expectException(HappyArborDayException::class);
        $this->expectExceptionMessage('wood');

        $listenerProvider = new ArrayListenerProvider([
            TestTask::class => [
                function () {
                    throw new HappyArborDayException('wood');
                },
            ],
        ]);

        (new Processor($listenerProvider))->process(new TestTask());
    }

    public function testProcessReturnsTheSameInstanceItWasPassed()
    {
        $task = new TestTask();
        $listenerProvider = new ArrayListenerProvider([
            TestTask::class => [
                function (TestTask $task) {
                    $task->flip = true;
                },
            ],
        ]);

        self::assertFalse($task->flip);

        $returnedTask = (new Processor($listenerProvider))->process($task);

        self::assertTrue($task->flip);
        self::assertSame($task, $returnedTask);
    }
}
