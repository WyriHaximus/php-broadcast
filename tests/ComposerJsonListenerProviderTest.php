<?php declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use ApiClients\Tools\TestUtilities\TestCase;
use Psr\Container\ContainerInterface;
use WyriHaximus\Broadcast\ComposerJsonListenerProvider;
use function WyriHaximus\iteratorOrArrayToArray;

/**
 * @internal
 */
final class ComposerJsonListenerProviderTest extends TestCase
{
    public function testProvideDummyEvent(): void
    {
        $listener = new DummyEventListener();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get(DummyEventListener::class)->shouldBeCalled()->willReturn($listener);

        $listenerProvider = new ComposerJsonListenerProvider('extra.wyrihaximus.broadcast.testing', $container->reveal());
        $listeners = iteratorOrArrayToArray($listenerProvider->getListenersForEvent(new \stdClass()));

        self::assertCount(1, $listeners);
        self::assertInstanceOf(DummyEventListener::class, $listeners[0]);
        self::assertSame($listener, $listeners[0]);
    }
}
