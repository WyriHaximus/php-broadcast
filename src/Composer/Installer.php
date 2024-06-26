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
use Roave\BetterReflection\Reflection\ReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionIntersectionType;
use Roave\BetterReflection\Reflection\ReflectionUnionType;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use WyriHaximus\Broadcast\Contracts\AsyncListener;
use WyriHaximus\Broadcast\Contracts\DoNotHandle;
use WyriHaximus\Broadcast\Contracts\Listener;

use function array_key_exists;
use function array_map;
use function class_exists;
use function count;
use function defined;
use function dirname;
use function explode;
use function file_exists;
use function function_exists;
use function in_array;
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
use function sprintf;
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

// phpcs:disable
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

        $rootVendorDir = $composer->getConfig()->get('vendor-dir');
        if (!is_string($rootVendorDir) || !file_exists($rootVendorDir)) { /** @phpstan-ignore-line */
            throw new \RuntimeException('Expecting vendor-dir to to be a string and an existing path.');
        }
        if (! function_exists('WyriHaximus\iteratorOrArrayToArray')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $rootVendorDir . '/wyrihaximus/iterator-or-array-to-array/src/functions_include.php';
        }

        if (! function_exists('WyriHaximus\listClassesInDirectories')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $rootVendorDir . '/wyrihaximus/list-classes-in-directory/src/functions_include.php';
        }

        if (! function_exists('WyriHaximus\getIn')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $rootVendorDir . '/wyrihaximus/string-get-in/src/functions_include.php';
        }

        if (! defined('WyriHaximus\Constants\Numeric\ONE')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $rootVendorDir . '/wyrihaximus/constants/src/Numeric/constants_include.php';
        }

        if (! defined('WyriHaximus\Constants\Boolean\TRUE')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $rootVendorDir . '/wyrihaximus/constants/src/Boolean/constants_include.php';
        }

        if (! function_exists('igorw\get_in')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $rootVendorDir . '/igorw/get-in/src/get_in.php';
        }

        if (! class_exists(PhpStormStubsMap::class)) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $rootVendorDir . '/jetbrains/phpstorm-stubs/PhpStormStubsMap.php';
        }

        if (! function_exists('Safe\file_get_contents')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $rootVendorDir . '/thecodingmachine/safe/generated/filesystem.php';
        }

        if (! function_exists('Safe\sprintf')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $rootVendorDir . '/thecodingmachine/safe/generated/strings.php';
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
        $vendorDir = $composerConfig->get('vendor-dir');
        if (!is_string($vendorDir) || !file_exists($vendorDir)) { /** @phpstan-ignore-line */
            throw new Exception('vendor-dir most be a string'); // @phpstan-ignore-line
        }

        // You're on your own
        if ($rootPackage->getName() === 'wyrihaximus/broadcast') {
            return dirname($vendorDir);
        }

        return $vendorDir . '/wyrihaximus/broadcast';
    }

    /**
     * @return array<string, non-empty-list<array{async: bool, class: string, method: string, static: bool}>>
     */
    private static function getRegisteredListeners(Composer $composer, IOInterface $io): array
    {
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        if (!is_string($vendorDir) || !file_exists($vendorDir)) { /** @phpstan-ignore-line */
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
        /**
         * @var non-empty-list<array{async: bool, class: string, method: string, static: bool, event: string}>
         */
        $flatEvents = (new Collection(
            (new Collection($packages))->filter(static function (PackageInterface $package): bool {
                return (bool) count($package->getAutoload());
            })->filter(static function (PackageInterface $package): bool {
                /**
                 * @psalm-suppress MixedArgumentTypeCoercion
                 */
                return (bool) getIn($package->getExtra(), 'wyrihaximus.broadcast.has-listeners', FALSE_);
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
                return file_exists($path); /** @phpstan-ignore-line */
            })->toArray()
        ))->flatMap(static function (string $path): array { /** @phpstan-ignore-line */
            return iteratorOrArrayToArray((static function () use ($path): iterable {
                // phpcs:disable
                if (is_dir($path)) { /** @phpstan-ignore-line */
                    yield from listClassesInDirectories($path);
                }

                if (is_file($path)) { /** @phpstan-ignore-line */
                    yield from listClassesInFiles($path);
                }
                // phpcs:enable
            })());
        })->flatMap(static function (string $class) use ($classReflector, $io): array { /** @phpstan-ignore-line */ // phpcs:disabled
            try {
                /** @psalm-suppress PossiblyUndefinedVariable */
                return [
                    (static function (ReflectionClass $reflectionClass): ReflectionClass {
                        /**
                         * Unit tests will fail if this line isn't here, getMethods will also do the trick
                         * Assuming any actual class properties reading will trigger it to be loaded
                         * Which will unit tests cause to succeed and not complain about
                         * WyriHaximus\Broadcast\Generated\AbstractListenerProvider not being found
                         * @psalm-suppress UnusedMethodCall
                         */
                        $reflectionClass->getInterfaces();

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
        })->filter(static function (ReflectionClass $class): bool { // phpcs:disabled
            return $class->isInstantiable();
        })->filter(static function (ReflectionClass $class): bool { // phpcs:disabled
            return $class->implementsInterface(Listener::class) || $class->implementsInterface(AsyncListener::class);
        })->flatMap(static function (ReflectionClass $class): array { // phpcs:disabled
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

                if (in_array(DoNotHandle::class, array_map(static fn (ReflectionAttribute $ra): string => $ra->getName(), $method->getAttributes()), true)) {
                    continue;
                }

                $eventTypeHolder = $method->getParameters()[ZERO]->getType();
                if ($eventTypeHolder instanceof ReflectionIntersectionType) {
                    continue;
                } elseif ($eventTypeHolder instanceof ReflectionUnionType) {
                    $eventTypes = $eventTypeHolder->getTypes();
                } else {
                    $eventTypes = [$eventTypeHolder];
                }

                foreach ($eventTypes as $eventType) {
                    $events[] = [
                        'event' => (string) $eventType,
                        'class' => $class->getName(),
                        'method' => $method->getName(),
                        'static' => $method->isStatic(),
                        'async' => $class->implementsInterface(AsyncListener::class),
                    ];
                }
            }

            return $events;
        })->toArray();

        $io->write(sprintf('<info>wyrihaximus/broadcast:</info> Found %s listener(s)', count($flatEvents)));
        $events = [];

        foreach ($flatEvents as $flatEvent) {
            $events[$flatEvent['event']][] = [
                'class' => $flatEvent['class'],
                'method' => $flatEvent['method'],
                'static' => $flatEvent['static'],
                'async' => $flatEvent['async'],
            ];
        }

        return $events;
    }
}
// phpcs:enable
