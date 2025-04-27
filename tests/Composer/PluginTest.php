<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast\Composer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Broadcast\Composer\Plugin;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;
use WyriHaximus\TestUtilities\TestCase;

final class PluginTest extends TestCase
{
    /** @return iterable<string, array{LogStages, string}> */
    public static function logStagesProvider(): iterable
    {
        yield LogStages::Init->name => [LogStages::Init, 'Locating listeners'];
        yield LogStages::Error->name => [LogStages::Error, 'An error occurred: %s'];
        yield LogStages::Collected->name => [LogStages::Collected, 'Found %d listener(s)'];
        yield LogStages::Completion->name => [LogStages::Completion, 'Generated static abstract listeners provider in %s second(s)'];
    }

    #[DataProvider('logStagesProvider')]
    #[Test]
    public function log(LogStages $stage, string $expectedLogLine): void
    {
        self::assertSame($expectedLogLine, Plugin::log($stage));
    }
}
