<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePluginExecutioner;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\Order;

final class Installer implements PluginInterface, EventSubscriberInterface
{
    /** @return array<string, array<string|int>> */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::PRE_AUTOLOAD_DUMP => ['findEventListeners', Order::EVERYONE_ALSO_MUST_TO_GO_BEFORE_ME + 1]];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    /**
     * Called before every dump autoload, generates a fresh PHP class.
     *
     * @api
     */
    public static function findEventListeners(Event $event): void
    {
        GenerativePluginExecutioner::execute($event->getComposer(), $event->getIO(), new Plugin());
    }
}
