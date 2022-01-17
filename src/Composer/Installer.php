<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Exception;
use Illuminate\Support\Collection;
use JetBrains\PHPStormStub\PhpStormStubsMap;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use WyriHaximus\Broadcast\Contracts\Listener;

use function array_key_exists;
use function class_exists;
use function count;
use function defined;
use function dirname;
use function explode;
use function file_exists;
use function function_exists;
use function is_dir;
use function is_file;
use function is_string;
use function microtime;
use function round;
use function rtrim;
use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\sprintf;
use function str_replace;
use function strpos;
use function var_export;
use function WyriHaximus\getIn;
use function WyriHaximus\iteratorOrArrayToArray;
use function WyriHaximus\listClassesInDirectories;
use function WyriHaximus\listClassesInFiles;

use const DIRECTORY_SEPARATOR;
use const PHP_INT_MIN;
use const WyriHaximus\Constants\Boolean\FALSE_;
use const WyriHaximus\Constants\Boolean\TRUE_;
use const WyriHaximus\Constants\Numeric\ONE;
use const WyriHaximus\Constants\Numeric\TWO;
use const WyriHaximus\Constants\Numeric\ZERO;

final class Installer implements PluginInterface, EventSubscriberInterface
{
    /**
     * @return array<string, array<string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::POST_AUTOLOAD_DUMP => ['findEventListeners', PHP_INT_MIN]];
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
     */
    public static function findEventListeners(Event $event): void
    {
        $start    = microtime(true);
        $io       = $event->getIO();
        $composer = $event->getComposer();

        if (! function_exists('WyriHaximus\iteratorOrArrayToArray')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/iterator-or-array-to-array/src/functions_include.php';
        }

        if (! function_exists('WyriHaximus\listClassesInDirectories')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/list-classes-in-directory/src/functions_include.php';
        }

        if (! function_exists('WyriHaximus\getIn')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/string-get-in/src/functions_include.php';
        }

        if (! defined('WyriHaximus\Constants\Numeric\ONE')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/constants/src/Numeric/constants_include.php';
        }

        if (! defined('WyriHaximus\Constants\Boolean\TRUE')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/constants/src/Boolean/constants_include.php';
        }

        if (! function_exists('igorw\get_in')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/igorw/get-in/src/get_in.php';
        }

        if (! class_exists(PhpStormStubsMap::class)) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/jetbrains/phpstorm-stubs/PhpStormStubsMap.php';
        }

        if (! function_exists('Safe\file_get_contents')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/thecodingmachine/safe/generated/filesystem.php';
        }

        if (! function_exists('Safe\sprintf')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/thecodingmachine/safe/generated/strings.php';
        }

        $io->write('<info>wyrihaximus/broadcast:</info> Locating listeners');

        $listeners = self::getRegisteredListeners($composer, $io);

        $io->write('<info>wyrihaximus/broadcast:</info> Found ' . count($listeners) . ' event(s)');

        $classContents = sprintf(
            str_replace(
                "['%s']",
                '%s',
                file_get_contents(
                    self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage()) . '/etc/AbstractListenerProvider.php'
                )
            ),
            var_export($listeners, TRUE_)
        );
        $installPath   = self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage())
            . '/src/Generated/AbstractListenerProvider.php';

        file_put_contents($installPath, $classContents);
        chmod($installPath, 0664);

        $io->write(sprintf(
            '<info>wyrihaximus/broadcast:</info> Generated static abstract listeners provider in %s second(s)',
            round(microtime(TRUE_) - $start, TWO)
        ));
    }

    /**
     * Find the location where to put the generate PHP class in.
     */
    private static function locateRootPackageInstallPath(
        Config $composerConfig,
        RootPackageInterface $rootPackage
    ): string {
        // You're on your own
        if ($rootPackage->getName() === 'wyrihaximus/broadcast') {
            $vendorDir = $composerConfig->get('vendor-dir');
            if (! is_string($vendorDir)) {
                throw new Exception('vendor-dir most be a string'); // @phpstan-ignore-line
            }

            return dirname($vendorDir);
        }

        return $composerConfig->get('vendor-dir') . '/wyrihaximus/broadcast';
    }

    /**
     * @return array<string, non-empty-list<array{class: mixed, method: mixed, static: mixed}>>
     */
    private static function getRegisteredListeners(Composer $composer, IOInterface $io): array
    {
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        if (! is_string($vendorDir)) {
            throw new Exception('vendor-dir most be a string'); // @phpstan-ignore-line
        }

        retry:
        try {
            $classReflector = new DefaultReflector(
                (new MakeLocatorForComposerJsonAndInstalledJson())(dirname($vendorDir), (new BetterReflection())->astLocator()),
            );
        } catch (InvalidPrefixMapping $invalidPrefixMapping) {
            mkdir(explode('" is not a', explode('" for prefix "', $invalidPrefixMapping->getMessage())[ONE])[ZERO]);
            goto retry;
        }

        $packages   = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $packages[] = $composer->getPackage();
        $flatEvents = (new Collection(
            (new Collection($packages))->filter(static function (PackageInterface $package): bool {
                return (bool) count($package->getAutoload());
            })->filter(static function (PackageInterface $package): bool {
                /**
                 * @psalm-suppress NullableReturnStatement
                 * @phpstan-ignore-next-line
                 */
                return getIn($package->getExtra(), 'wyrihaximus.broadcast.has-listeners', FALSE_);
            })->filter(static function (PackageInterface $package): bool {
                return array_key_exists('classmap', $package->getAutoload()) || array_key_exists('psr-4', $package->getAutoload());
            })->flatMap(static function (PackageInterface $package) use ($vendorDir): array {
                $packageName = $package->getName();
                $autoload    = $package->getAutoload();
                $paths       = [];

                if (array_key_exists('psr-4', $autoload)) {
                    foreach ($autoload['psr-4'] as $path) {
                        if (is_string($path)) {
                            if ($package instanceof RootPackageInterface) {
                                $paths[] = dirname($vendorDir) . DIRECTORY_SEPARATOR . $path;
                                continue;
                            }

                            $paths[] = $vendorDir . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . $path;
                            continue;
                        }
                    }
                }

                if (array_key_exists('classmap', $autoload)) {
                    foreach ($autoload['classmap'] as $path) {
                        if ($package instanceof RootPackageInterface) {
                            $paths[] = dirname($vendorDir) . DIRECTORY_SEPARATOR . $path;
                            continue;
                        }

                        $paths[] = $vendorDir . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . $path;
                    }
                }

                return $paths;
            })->map(static function (string $path): string {
                return rtrim($path, '/');
            })->filter(static function (string $path): bool {
                return file_exists($path);
            })->toArray()
        ))->flatMap(static function (string $path): array {
            return iteratorOrArrayToArray((static function () use ($path): iterable {
                // phpcs:disable
                if (is_dir($path)) {
                    yield from listClassesInDirectories($path);
                }

                if (is_file($path)) {
                    yield from listClassesInFiles($path);
                }
                // phpcs:enable
            })());
        })->flatMap(static function (string $class) use ($classReflector, $io): array {
            try {
                /** @psalm-suppress PossiblyUndefinedVariable */
                return [
                    (static function (ReflectionClass $reflectionClass): ReflectionClass {
                        $reflectionClass->getInterfaces();
                        $reflectionClass->getMethods();

                        return $reflectionClass;
                    })($classReflector->reflectClass($class)),
                ];
            } catch (IdentifierNotFound $identifierNotFound) {
                $io->write(sprintf(
                    '<info>wyrihaximus/broadcast:</info> Error while reflecting "<fg=cyan>%s</>": <fg=yellow>%s</>',
                    $class,
                    $identifierNotFound->getMessage()
                ));
            }

            return [];
        })->filter(static function (ReflectionClass $class): bool {
            return $class->isInstantiable();
        })->filter(static function (ReflectionClass $class): bool {
            return $class->implementsInterface(Listener::class);
        })->flatMap(static function (ReflectionClass $class): array {
            $events = [];

            foreach ($class->getMethods() as $method) {
                if (! $method->isPublic()) {
                    continue;
                }

                if (strpos($method->getName(), '__') === ZERO) {
                    continue;
                }

                if ($method->getNumberOfParameters() !== ONE) {
                    continue;
                }

                $events[] = [
                    'event' => (string) $method->getParameters()[ZERO]->getType(),
                    'class' => $class->getName(),
                    'method' => $method->getName(),
                    'static' => $method->isStatic(),
                ];
            }

            return $events;
        })->toArray();

        $io->write(sprintf('<info>wyrihaximus/broadcast:</info> Found %s listener(s)', count($flatEvents)));
        $events = [];

        foreach ($flatEvents as $flatEvent) {
            $events[(string) $flatEvent['event']][] = [
                'class' => $flatEvent['class'],
                'method' => $flatEvent['method'],
                'static' => $flatEvent['static'],
            ];
        }

        return $events;
    }
}
