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
use Symfony\Component\Console\Output\StreamOutput;
use WyriHaximus\Broadcast\Composer\Installer;
use WyriHaximus\Broadcast\Dummy\Listener;
use WyriHaximus\TestUtilities\TestCase;

use function closedir;
use function dirname;
use function file_exists;
use function fseek;
use function in_array;
use function is_dir;
use function is_file;
use function readdir;
use function Safe\copy;
use function Safe\file_get_contents;
use function Safe\fileperms;
use function Safe\fopen;
use function Safe\mkdir;
use function Safe\opendir;
use function Safe\stream_get_contents;
use function Safe\unlink;
use function sprintf;
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
                $this->output = new StreamOutput(fopen('php://memory', 'rw'), decorated: false);
            }

            public function output(): string
            {
                fseek($this->output->getStream(), 0);

                return stream_get_contents($this->output->getStream());
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

        $this->recurseCopy(dirname(dirname(__DIR__)) . '/', $this->getTmpDir());

        $fileName = $this->getTmpDir() . 'src/Generated/AbstractListenerProvider.php';
        if (file_exists($fileName)) {
            unlink($fileName);
        }

        self::assertFileDoesNotExist($fileName);

        // Do the actual generating
        Installer::findEventListeners($event);

        $output = $io->output();

        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Locating listeners', $output);
        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Locating listeners', $output);
        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Generated static abstract listeners provider in ', $output);
        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Found 4 listener(s)', $output);
        self::assertStringContainsString('<error>wyrihaximus/broadcast:</error> An error occurred: Cannot reflect "<fg=cyan>WyriHaximus\Broadcast\ContainerListenerProvider</>": <fg=yellow>Roave\BetterReflection\Reflection\ReflectionClass "WyriHaximus\Broadcast\Generated\AbstractListenerProvider" could not be found in the located source</>', $output);

        self::assertFileExists($fileName);
        self::assertTrue(in_array(
            substr(sprintf('%o', fileperms($fileName)), -4),
            [
                '0664',
                '0666',
            ],
            true,
        ));
        $fileContents = file_get_contents($fileName);
        self::assertStringContainsStringIgnoringCase('private const array LISTENERS = array (', $fileContents);
        self::assertStringNotContainsStringIgnoringCase("private const LISTENERS = array (\r);", $fileContents);
        self::assertStringNotContainsStringIgnoringCase("private const LISTENERS = array (\r\n);", $fileContents);
        self::assertStringNotContainsStringIgnoringCase("private const LISTENERS = array (\n);", $fileContents);
        self::assertStringNotContainsStringIgnoringCase('Event|stdClass', $fileContents);
        self::assertStringContainsStringIgnoringCase('\'class\' => \'WyriHaximus\\\\Broadcast\\\\Dummy\\\\Listener\'', $fileContents);
        self::assertStringContainsStringIgnoringCase('\'method\' => \'handle\'', $fileContents);
        self::assertStringContainsStringIgnoringCase('\'method\' => \'handleBoth\'', $fileContents);
        self::assertStringContainsStringIgnoringCase('\'static\' => false', $fileContents);
        self::assertStringContainsStringIgnoringCase('\'async\' => false', $fileContents);
        self::assertStringContainsStringIgnoringCase('\'WyriHaximus\\\\Broadcast\\\\Dummy\\\\Event\' => ', $fileContents);
        self::assertStringContainsStringIgnoringCase('\'stdClass\' => ', $fileContents);
        self::assertStringNotContainsStringIgnoringCase('\'method\' => \'doNotHandle\'', $fileContents);
        self::assertStringNotContainsStringIgnoringCase('\'method\' => \'__construct\'', $fileContents);
        self::assertStringNotContainsStringIgnoringCase('\'method\' => \'__destruct\'', $fileContents);
        self::assertStringNotContainsStringIgnoringCase('\'method\' => \'doNotHandleDueToTwoArguments\'', $fileContents);
        self::assertStringNotContainsStringIgnoringCase('\'method\' => \'doNotHandlePrivate\'', $fileContents);
        self::assertStringNotContainsStringIgnoringCase('\'method\' => \'doNotHandleProtected\'', $fileContents);
    }

    private function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (! file_exists($dst)) {
            mkdir($dst);
        }

        while (( $file = readdir($dir)) !== false) {
            if (( $file === '.' ) || ( $file === '..' )) {
                continue;
            }

            if (is_dir($src . '/' . $file)) {
                $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
            } elseif (is_file($src . '/' . $file)) {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }

        closedir($dir);
    }
}
