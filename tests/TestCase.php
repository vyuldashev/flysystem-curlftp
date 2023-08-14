<?php

declare(strict_types=1);

namespace VladimirYuldashev\Flysystem\Tests;

use Faker\Factory;
use League\Flysystem\Config;
use League\Flysystem\Visibility;
use PHPUnit\Framework\TestCase as BaseTestCase;
use VladimirYuldashev\Flysystem\CurlFtpAdapter;
use VladimirYuldashev\Flysystem\CurlFtpConnectionOptions;

abstract class TestCase extends BaseTestCase
{
    protected $root = '';

    /** @var CurlFtpAdapter */
    protected $adapter;

    public function setUp(): void
    {
        $this->root = getenv('FTP_ADAPTER_ROOT');
        $this->createResourceDir('/');

        $this->adapter = new CurlFtpAdapter(
            CurlFtpConnectionOptions::fromArray([
                'protocol' => getenv('FTP_ADAPTER_PROTOCOL'),
                'host' => getenv('FTP_ADAPTER_HOST'),
                'port' => (int) getenv('FTP_ADAPTER_PORT'),
                'username' => getenv('FTP_ADAPTER_USER'),
                'password' => getenv('FTP_ADAPTER_PASSWORD'),
                'timeout' => getenv('FTP_ADAPTER_TIMEOUT') ?: 35,
                'root' => $this->root,
                'utf8' => true,
                'ssl' => false,
                'ftps' => (bool) (getenv('FTP_ADAPTER_FTPS') ?: false), // use ftps:// with implicit TLS or ftp:// with explicit TLS
                'passive' => true, // default use PASV mode
                'ignorePassiveAddress' => true, // ignore the IP address in the PASV response
                'timestampsOnUnixListingsEnabled' => true,
                'sslVerifyPeer' => 0, // using 0 is insecure, use it only if you know what you're doing
                'sslVerifyHost' => 0, // using 0 is insecure, use it only if you know what you're doing
                'verbose' => false, // set verbose mode on/off
            ])
        );
    }

    public function tearDown(): void
    {
        $this->adapter = null;
        unset($this->adapter);
        $this->clearResources();
    }

    protected function getResourcesPath()
    {
        return __DIR__ . '/resources/';
    }

    protected function getResourceContent($path)
    {
        $absolutePath = $this->getResourceAbsolutePath($path);
        $this->assertIsReadable($absolutePath);

        return file_get_contents($absolutePath);
    }

    protected function getResourceAbsolutePath($path)
    {
        return implode('/', array_filter([
            rtrim($this->getResourcesPath(), '/'),
            trim($this->root, '/'),
            ltrim($path, '/'),
        ]));
    }

    protected function createResourceDirIfPathHasDir($path): void
    {
        $pathDir = pathinfo($path, PATHINFO_DIRNAME);
        if ($pathDir !== '.') {
            $this->createResourceDir($pathDir);
        }
    }

    protected function createResourceDir($path): void
    {
        if (empty($path)) {
            return;
        }
        $absolutePath = $this->getResourceAbsolutePath($path);
        if ( ! is_dir($absolutePath)) {
            $umask = umask(0);
            mkdir($absolutePath, 0777, true);
            umask($umask);
        }
    }

    protected function createResourceFile($path, $filedata = ''): void
    {
        $this->createResourceDir(dirname($path));
        $absolutePath = $this->getResourceAbsolutePath($path);
        file_put_contents($absolutePath, $filedata);
    }

    protected static function randomFileName()
    {
        return self::faker()->name . '.' . self::faker()->fileExtension;
    }

    protected static function faker()
    {
        return Factory::create();
    }

    protected function clearResources(): void
    {
        exec('rm -rf ' . escapeshellarg($this->getResourcesPath()) . '*');
        exec('rm -rf ' . escapeshellarg($this->getResourcesPath()) . '.* 2>/dev/null');
        clearstatcache();
    }

    protected static function publicConfig()
    {
        return new Config(
            [
                Config::OPTION_VISIBILITY => Visibility::PUBLIC,
                Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC
            ]
        );
    }
}
