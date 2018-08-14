<?php declare(strict_types=1);

namespace WyriHaximus\Broadcast;

use Psr\Event\Dispatcher\MessageInterface;

abstract class Task implements MessageInterface
{
}
