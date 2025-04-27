<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use RuntimeException;
use WyriHaximus\Broadcast\Contracts\AsyncListener;
use WyriHaximus\Broadcast\Contracts\Listener;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;

use function chmod;
use function file_get_contents;
use function file_put_contents;
use function is_string;
use function sprintf;
use function str_replace;
use function var_export;

final class Plugin implements GenerativePlugin
{
    public static function name(): string
    {
        return 'wyrihaximus/broadcast';
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
        yield new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', true);
        yield new ImplementsInterface(Listener::class, AsyncListener::class);
        yield new IsInstantiable();
    }

    /** @inheritDoc */
    public function collectors(): iterable
    {
        yield new Collector();
    }

    public function compile(string $rootPath, ItemContract ...$items): void
    {
        $listeners = [];
        foreach ($items as $item) {
            if (! ($item instanceof Item)) {
                continue;
            }

            $listeners[$item->event][] = $item->jsonSerialize();
        }

        $template = file_get_contents($rootPath . '/etc/AbstractListenerProvider.php');

        if (! is_string($template)) {
            throw new RuntimeException('Unable to read template');
        }

        $classContents = sprintf(
            str_replace(
                "['%s']",
                '%s',
                $template,
            ),
            var_export($listeners, true),
        );
        $installPath   = $rootPath . '/src/Generated/AbstractListenerProvider.php';

        file_put_contents($installPath, $classContents);
        chmod($installPath, 0664);
    }
}
