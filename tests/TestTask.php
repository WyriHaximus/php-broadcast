<?php declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

use WyriHaximus\Broadcast\Task;

final class TestTask extends Task
{
    /** @var bool  */
    public $flip = false;
}
