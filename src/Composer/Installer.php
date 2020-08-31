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
use JetBrains\PHPStormStub\PhpStormStubsMap;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use Rx\Observable;
use Throwable;
use WyriHaximus\Broadcast\Contracts\Listener;

use function ApiClients\Tools\Rx\observableFromArray;
use function array_filter;
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
        return [ScriptEvents::PRE_AUTOLOAD_DUMP => ['findEventListeners', PHP_INT_MIN]];
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

        if (! function_exists('React\Promise\Resolve')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/react/promise/src/functions_include.php';
        }

        if (! function_exists('ApiClients\Tools\Rx\observableFromArray')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/api-clients/rx/src/functions_include.php';
        }

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
            return dirname($composerConfig->get('vendor-dir'));
        }

        return $composerConfig->get('vendor-dir') . '/wyrihaximus/broadcast';
    }

    /**
     * @return array<string, array<array{class: string, method: string, static: bool}>>
     */
    private static function getRegisteredListeners(Composer $composer, IOInterface $io): array
    {
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        retry:
        try {
            $classReflector = new ClassReflector(
                (new MakeLocatorForComposerJsonAndInstalledJson())(dirname($vendorDir), (new BetterReflection())->astLocator()),
            );
        } catch (InvalidPrefixMapping $invalidPrefixMapping) {
            mkdir(explode('" is not a', explode('" for prefix "', $invalidPrefixMapping->getMessage())[ONE])[ZERO]);
            goto retry;
        }

        $result     = [];
        $packages   = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $packages[] = $composer->getPackage();
        observableFromArray($packages)->filter(static function (PackageInterface $package): bool {
            return (bool) count($package->getAutoload());
        })->filter(static function (PackageInterface $package): bool {
            return getIn($package->getExtra(), 'wyrihaximus.broadcast.has-listeners', FALSE_);
        })->filter(static function (PackageInterface $package): bool {
            return array_key_exists('classmap', $package->getAutoload()) || array_key_exists('psr-4', $package->getAutoload());
        })->flatMap(static function (PackageInterface $package) use ($vendorDir): Observable {
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

            return observableFromArray($paths);
        })->map(static function (string $path): string {
            return rtrim($path, '/');
        })->filter(static function (string $path): bool {
            return file_exists($path);
        })->toArray()->flatMap(static function (array $paths): Observable {
            return observableFromArray(
                iteratorOrArrayToArray((static function () use ($paths): iterable {
                    yield from listClassesInDirectories(...array_filter($paths, static function (string $path): bool {
                        return is_dir($path);
                    }));
                    yield from listClassesInFiles(...array_filter($paths, static function (string $path): bool {
                        return is_file($path);
                    }));
                })())
            );
        })->flatMap(static function (string $class) use ($classReflector, $io): Observable {
            try {
                /** @psalm-suppress PossiblyUndefinedVariable */
                return observableFromArray([
                    (static function (ReflectionClass $reflectionClass): ReflectionClass {
                        $reflectionClass->getInterfaces();
                        $reflectionClass->getMethods();

                        return $reflectionClass;
                    })($classReflector->reflect($class)),
                ]);
            } catch (IdentifierNotFound $identifierNotFound) {
                $io->write(sprintf(
                    '<info>wyrihaximus/broadcast:</info> Error while reflecting "<fg=cyan>%s</>": <fg=yellow>%s</>',
                    $class,
                    $identifierNotFound->getMessage()
                ));
            }

            return observableFromArray([]);
        })->filter(static function (ReflectionClass $class): bool {
            return $class->isInstantiable();
        })->filter(static function (ReflectionClass $class): bool {
            return $class->implementsInterface(Listener::class);
        })->flatMap(static function (ReflectionClass $class): Observable {
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

            return observableFromArray($events);
        })->toArray()->toPromise()->then(static function (array $flatEvents) use (&$result, $io): void {
            $io->write(sprintf('<info>wyrihaximus/broadcast:</info> Found %s listener(s)', count($flatEvents)));
            $events = [];

            foreach ($flatEvents as $flatEvent) {
                $events[(string) $flatEvent['event']][] = [
                    'class' => $flatEvent['class'],
                    'method' => $flatEvent['method'],
                    'static' => $flatEvent['static'],
                ];
            }

            $result = $events;
        })->then(null, static function (Throwable $throwable) use ($io): void {
            $io->write(sprintf('<info>wyrihaximus/broadcast:</info> Unexpected error: <fg=red>%s</>', $throwable->getMessage()));
        });

        return $result;
    }
}
