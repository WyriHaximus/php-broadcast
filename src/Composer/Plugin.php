<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use WyriHaximus\Broadcast\Contracts\AsyncListener as AsyncListenerContract;
use WyriHaximus\Broadcast\Contracts\Listener as ListenerContract;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\LogicalOr;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonRequiresSpecificPackage;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\PackageType;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\Remove;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\TwigFile;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;

final class Plugin implements GenerativePlugin
{
    private const string PACKAGE_NAME = 'wyrihaximus/broadcast';

    public static function name(): string
    {
        return self::PACKAGE_NAME;
    }

    public static function log(LogStages $stage): string
    {
        return match ($stage) {
            LogStages::Init => 'Locating listeners',
            LogStages::Error => 'An error occurred: %s',
            LogStages::Collected => 'Found %d listener(s)',
            LogStages::Completion => 'Generated static abstract listeners provider in %s second(s)',
        };
    }

    /** @inheritDoc */
    public function filters(): iterable
    {
        yield from LogicalOr::create(
            new ComposerJsonRequiresSpecificPackage(self::PACKAGE_NAME, PackageType::PRODUCTION),
            new ComposerJsonRequiresSpecificPackage(self::PACKAGE_NAME, PackageType::DEVELOPMENT),
            new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', true),
        );

        yield new ImplementsInterface(ListenerContract::class, AsyncListenerContract::class);
        yield new IsInstantiable();
    }

    /** @inheritDoc */
    public function collectors(): iterable
    {
        yield new Collector();
    }

    public function compile(string $rootPath, ItemContract ...$items): void
    {
        Remove::directoryContents($rootPath . '/src/Generated/');

        $events = [];
        foreach ($items as $item) {
            if (! ($item instanceof Listener)) {
                continue;
            }

            $events[$item->event][] = $item->jsonSerialize();
        }

        TwigFile::render(
            $rootPath . '/etc/AbstractListenerProvider.php.twig',
            $rootPath . '/src/Generated/AbstractListenerProvider.php',
            ['events' => $events],
        );
    }
}
