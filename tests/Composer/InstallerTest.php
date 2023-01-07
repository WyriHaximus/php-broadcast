<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Broadcast\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Prophecy\Argument;
use WyriHaximus\Broadcast\Composer\Installer;
use WyriHaximus\TestUtilities\TestCase;

use function closedir;
use function dirname;
use function file_exists;
use function fileperms;
use function is_dir;
use function readdir;
use function Safe\copy;
use function Safe\file_get_contents;
use function Safe\mkdir;
use function Safe\opendir;
use function Safe\sprintf;
use function Safe\substr;
use function Safe\unlink;

use const DIRECTORY_SEPARATOR;

final class InstallerTest extends TestCase
{
    /**
     * @test
     */
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
        /** @phpstan-ignore-next-line */
        $io = $this->prophesize(IOInterface::class);
        $io->debug('Checked CA file /etc/pki/tls/certs/ca-bundle.crt does not exist or it is not a file.')->shouldBeCalled();
        $io->debug('Checked directory /etc/pki/tls/certs/ca-bundle.crt does not exist or it is not a directory.')->shouldBeCalled();
        $io->debug('Checked CA file /etc/ssl/certs/ca-certificates.crt: valid')->shouldBeCalled();
        $io->write('<info>wyrihaximus/broadcast:</info> Locating listeners')->shouldBeCalled();
        $io->write('<info>wyrihaximus/broadcast:</info> Found 2 event(s)')->shouldBeCalled();
        $io->write(Argument::containingString('<info>wyrihaximus/broadcast:</info> Generated static abstract listeners provider in '))->shouldBeCalled();
        $io->write(Argument::containingString('<info>wyrihaximus/broadcast:</info> Generated static abstract listeners provider in -'))->shouldNotBeCalled();
        $io->write('<info>wyrihaximus/broadcast:</info> Found 4 listener(s)')->shouldBeCalled();

        $io->write('<info>wyrihaximus/broadcast:</info> Error while reflecting "<fg=cyan>WyriHaximus\Broadcast\ContainerListenerProvider</>": <fg=yellow>Roave\BetterReflection\Reflection\ReflectionClass "WyriHaximus\Broadcast\Generated\AbstractListenerProvider" could not be found in the located source</>')->shouldBeCalled();

        /** @phpstan-ignore-next-line */
        $repository        = $this->prophesize(InstalledRepositoryInterface::class);
        $repositoryManager = new RepositoryManager($io->reveal(), $composerConfig, Factory::createHttpDownloader($io->reveal(), $composerConfig));
        $repositoryManager->setLocalRepository($repository->reveal());
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setPackage($rootPackage);
        $event = new Event(
            ScriptEvents::PRE_AUTOLOAD_DUMP,
            $composer,
            $io->reveal()
        );

        $installer = new Installer();

        // Test dead methods and make Infection happy
        $installer->activate($composer, $io->reveal());
        $installer->deactivate($composer, $io->reveal());
        $installer->uninstall($composer, $io->reveal());

        $this->recurseCopy(dirname(dirname(__DIR__)) . '/', $this->getTmpDir());

        $fileName = $this->getTmpDir() . 'src/Generated/AbstractListenerProvider.php';
        if (file_exists($fileName)) {
            unlink($fileName);
        }

        self::assertFileDoesNotExist($fileName);

        // Do the actual generating
        Installer::findEventListeners($event);

        self::assertFileExists($fileName);
        self::assertSame('0664', substr(sprintf('%o', fileperms($fileName)), -4));
        $fileContents = file_get_contents($fileName);
        self::assertStringContainsStringIgnoringCase('private const LISTENERS = array (', $fileContents);
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
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }

        closedir($dir);
    }
}
