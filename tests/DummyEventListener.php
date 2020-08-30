<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

final class DummyEventListener
{
    public function __invoke(object $event): void
    {
        // void
    }
}
