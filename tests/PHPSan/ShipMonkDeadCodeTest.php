<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast\PHPSan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WyriHaximus\Broadcast\Dummy\AsyncListener;
use WyriHaximus\Broadcast\Dummy\Listener;
use WyriHaximus\Broadcast\PHPSan\ShipMonkDeadCode;
use WyriHaximus\TestUtilities\TestCase;

#[CoversClass(ShipMonkDeadCode::class)]
final class ShipMonkDeadCodeTest extends TestCase
{
    #[Test]
    public function marksListenerMethodsAsUsed(): void
    {
        $method = new ReflectionMethod(Listener::class, 'handle');

        self::assertNotNull(new ShipMonkDeadCode()->shouldMarkMethodAsUsed($method));
    }

    #[Test]
    public function marksAsyncListenerMethodsAsUsed(): void
    {
        $method = new ReflectionMethod(AsyncListener::class, 'handle');

        self::assertNotNull(new ShipMonkDeadCode()->shouldMarkMethodAsUsed($method));
    }

    #[Test]
    public function ignoresNonListenerMethods(): void
    {
        $method = new ReflectionMethod(ShipMonkDeadCode::class, 'shouldMarkMethodAsUsed');

        self::assertNull(new ShipMonkDeadCode()->shouldMarkMethodAsUsed($method));
    }
}
