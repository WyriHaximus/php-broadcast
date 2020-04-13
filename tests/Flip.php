<?php declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast;

final class Flip
{
    private bool $flip = false;

    public function __construct()
    {
    }

    public function __invoke(): void
    {
        $this->flip = true;
    }

    public function flip(): bool
    {
        return $this->flip;
    }
}
