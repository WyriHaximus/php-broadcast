<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast\Dummy;

use WyriHaximus\Broadcast\Contracts\Listener as ListenerContract;

final class Listener implements ListenerContract
{
    public function handle(Event $event): void
    {
    }
}
