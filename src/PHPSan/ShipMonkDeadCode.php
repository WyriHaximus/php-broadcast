<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\PHPSan;

use Override;
use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use WyriHaximus\Broadcast\Contracts\AsyncListener;
use WyriHaximus\Broadcast\Contracts\Listener;

final class ShipMonkDeadCode extends ReflectionBasedMemberUsageProvider
{
    #[Override]
    public function shouldMarkMethodAsUsed(ReflectionMethod $method): VirtualUsageData|null
    {
        if ($method->getDeclaringClass()->implementsInterface(Listener::class)) {
            return VirtualUsageData::withNote('Class is a Broadcast (PSR-14 event dispatcher) Listener');
        }

        if ($method->getDeclaringClass()->implementsInterface(AsyncListener::class)) {
            return VirtualUsageData::withNote('Class is an Async (ReactPHP powered fibers) Broadcast (PSR-14 event dispatcher) Listener');
        }

        return null;
    }
}
