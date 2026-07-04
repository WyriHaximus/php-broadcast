<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Mockery;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Output\StreamOutput;
use WyriHaximus\Broadcast\Composer\Installer;
use WyriHaximus\Broadcast\Dummy\Listener;
use WyriHaximus\TestUtilities\TestCase;

use function closedir;
use function copy;
use function dirname;
use function file_exists;
use function file_get_contents;
use function fileperms;
use function fopen;
use function fseek;
use function in_array;
use function is_dir;
use function is_file;
use function is_resource;
use function mkdir;
use function opendir;
use function readdir;
use function sprintf;
use function str_replace;
use function stream_get_contents;
use function substr;

use const DIRECTORY_SEPARATOR;

#[CoversMethod(Listener::class, 'handleBoth')]
#[CoversMethod(Listener::class, 'doNotHandleDueToTwoArguments')]
#[CoversMethod(Listener::class, 'doNotHandleProtected')]
final class InstallerTest extends TestCase
{
    #[Test]
    public function generate(): void
    {
        $composerConfig = new Config();
        $composerConfig->merge([
            'config' => [
                'vendor-dir' => $this->getTmpDir() . 'vendor' . DIRECTORY_SEPARATOR,
            ],
        ]);
        $rootPackage = new RootPackage('wyrihaximus/broadcast', 'dev-master', 'dev-master');
        $rootPackage->setExtra([
            'wyrihaximus' => [
                'broadcast' => ['has-listeners' => true],
            ],
        ]);
        $rootPackage->setAutoload([
            'classmap' => ['dummy/event','dummy/listener/Listener.php'],
            'psr-4' => ['WyriHaximus\\Broadcast\\' => 'src'],
        ]);

        $io         = new class () extends NullIO {
            private readonly StreamOutput $output;

            public function __construct()
            {
                /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.fopen */
                $stream = fopen('php://memory', 'rw');
                if (! is_resource($stream)) {
                    throw new RuntimeException('Failed to open stream');
                }

                $this->output = new StreamOutput($stream, decorated: false);
            }

            public function output(): string
            {
                fseek($this->output->getStream(), 0);

                /** @phpstan-ignore ternary.shortNotAllowed */
                return stream_get_contents($this->output->getStream()) ?: '';
            }

            /** @inheritDoc */
            public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
            {
                $this->output->write($messages, $newline, $verbosity & StreamOutput::OUTPUT_RAW);
            }
        };
        $repository = Mockery::mock(InstalledRepositoryInterface::class);
        $repository->allows()->getCanonicalPackages()->andReturn([]);
        $repositoryManager = new RepositoryManager($io, $composerConfig, Factory::createHttpDownloader($io, $composerConfig));
        $repositoryManager->setLocalRepository($repository);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setPackage($rootPackage);
        $event = new Event(
            ScriptEvents::PRE_AUTOLOAD_DUMP,
            $composer,
            $io,
        );

        $installer = new Installer();

        // Test dead methods and make Infection happy
        $installer->activate($composer, $io);
        $installer->deactivate($composer, $io);
        $installer->uninstall($composer, $io);

        $this->recurseCopy(dirname(__DIR__, 2) . '/', $this->getTmpDir());

        $fileName = $this->getTmpDir() . 'src/ContainerListenerProvider.php';

        // Do the actual generating
        Installer::findEventListeners($event);

        $output = $io->output();

        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Locating listeners', $output);
        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Locating listeners', $output);
        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Generated static abstract listeners provider in ', $output);
        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Found 7 listener(s)', $output);

        self::assertFileExists($fileName);
        self::assertTrue(in_array(
            /** @phpstan-ignore ternary.shortNotAllowed */
            substr(sprintf('%o', fileperms($fileName) ?: 0), -4),
            [
                '0764',
                '0664',
                '0666',
            ],
            true,
        ));
        $previousFileContents = '';
        /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.fileGetContents,ternary.shortNotAllowed */
        $fileContents = file_get_contents($fileName) ?: '';
        while ($previousFileContents !== $fileContents) {
            $previousFileContents = $fileContents;
            $fileContents         = str_replace('  ', ' ', $fileContents);
        }

        self::assertStringContainsStringIgnoringCase('([$this->container->get(\WyriHaximus\Broadcast\Dummy\Listener::class), \'handle\'])', $fileContents);
        self::assertStringContainsStringIgnoringCase('([$this->container->get(\WyriHaximus\Broadcast\Dummy\Listener::class), \'handleBoth\'])', $fileContents);
        self::assertStringContainsStringIgnoringCase('\'\WyriHaximus\Broadcast\Dummy\Listener::handleBothStaticly\'', $fileContents);
        self::assertStringContainsStringIgnoringCase('fn (\WyriHaximus\Broadcast\Dummy\Event $event) => await(async(fn (\WyriHaximus\Broadcast\Dummy\Event $event) => $this->container->get(\WyriHaximus\Broadcast\Dummy\AsyncListener::class)->handle' . "\n" . ' ($event))($event))', $fileContents);
        self::assertStringContainsStringIgnoringCase('static fn (\WyriHaximus\Broadcast\Dummy\Event $event) => await(async(static fn (\WyriHaximus\Broadcast\Dummy\Event $event) => \WyriHaximus\Broadcast\Dummy\AsyncListener::handleStatic ($event))($event))', $fileContents);

        self::assertStringNotContainsStringIgnoringCase('\string::class => [', $fileContents);
        self::assertStringNotContainsStringIgnoringCase('thisShouldNotBeDetected', $fileContents);
    }

    private function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if ($dir === false) {
            return;
        }

        /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.fileExists */
        if (! file_exists($dst)) {
            /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.mkdir */
            mkdir($dst);
        }

        while (( $file = readdir($dir)) !== false) {
            if (( $file === '.' ) || ( $file === '..' )) {
                continue;
            }

            /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.isDir */
            if (is_dir($src . '/' . $file)) {
                $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
                /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.isFile */
            } elseif (is_file($src . '/' . $file)) {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }

        closedir($dir);
    }
}
