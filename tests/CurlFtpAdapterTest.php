<?php

namespace VladimirYuldashev\Flysystem\Tests;

use Faker\Factory;
use League\Flysystem\Config;
use VladimirYuldashev\Flysystem\CurlFtpAdapter;

class CurlFtpAdapterTest extends \PHPUnit_Framework_TestCase
{
    const RESOURCES_PATH = __DIR__.'/resources/';

    /** @var CurlFtpAdapter */
    protected $adapter;
    protected $root;

    protected function getResourceContent($path)
    {
        return file_get_contents($this->getResourceAbsolutePath($path));
    }

    protected function getResourceAbsolutePath($path)
    {
        return implode('/', array_filter([
            rtrim(static::RESOURCES_PATH, '/'),
            trim($this->root, '/'),
            ltrim($path, '/'),
        ]));
    }

    protected function createResourceDir($path)
    {
        if (empty($path)) {
            return;
        }
        $absolutePath = $this->getResourceAbsolutePath($path);
        if (!is_dir($absolutePath)) {
            $umask = umask(0);
            mkdir($absolutePath, 0777, true);
            umask($umask);
        }
    }

    protected function createResourceFile($path, $filedata = '')
    {
        $this->createResourceDir(dirname($path));
        $absolutePath = $this->getResourceAbsolutePath($path);
        file_put_contents($absolutePath, $filedata);
    }

    protected function clearResources()
    {
        exec('rm -rf '.escapeshellarg(static::RESOURCES_PATH).'*');
        exec('rm -rf '.escapeshellarg(static::RESOURCES_PATH).'.* 2>/dev/null');
        clearstatcache();
    }

    public function setUp()
    {
        $this->root = '';
        $this->createResourceDir('/');

        $this->adapter = new CurlFtpAdapter([
            'protocol' => getenv('FTP_ADAPTER_PROTOCOL'),
            'host' => getenv('FTP_ADAPTER_HOST'),
            'port' => getenv('FTP_ADAPTER_PORT'),
            'username' => getenv('FTP_ADAPTER_USER'),
            'password' => getenv('FTP_ADAPTER_PASSWORD'),
            'timeout' => getenv('FTP_ADAPTER_TIMEOUT') ?: 35,
            'root' => $this->root,
        ]);
    }

    public function tearDown()
    {
        unset($this->adapter);
        $this->clearResources();
    }

    /**
     * @dataProvider filesProvider
     */
    public function testRead($filename)
    {
        $filedata = $this->faker()->text;
        $this->createResourceFile($filename, $filedata);

        $response = $this->adapter->read($filename);
        $this->assertEquals($filedata, $response['contents']);
    }

    /**
     * @dataProvider filesProvider
     */
    public function testWrite($filename)
    {
        $filedata = $this->faker()->text;

        $this->adapter->write($filename, $filedata, new Config);
        $this->assertEquals($filedata, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesProvider
     */
    public function testHas($filename)
    {
        $filedata = $this->faker()->text;
        $this->createResourceFile($filename, $filedata);

        $this->assertTrue((bool) $this->adapter->has($filename));
    }

    /**
     * @dataProvider withSubFolderProvider
     */
    public function testHasInSubFolder($filepath)
    {
        $filedata = $this->faker()->text;
        $this->createResourceFile($filepath, $filedata);

        $this->assertTrue((bool) $this->adapter->has($filepath));
    }

    /**
     * @dataProvider withSubFolderProvider
     */
    public function testListContents($path)
    {
        $filedata = $this->faker()->text;
        $this->createResourceFile($path, $filedata);

        $this->assertCount(1, $this->adapter->listContents(dirname($path)));
    }

    public function filesProvider()
    {
        return [
            [$this->randomFileName()],
            [$this->randomFileName()],
            [$this->randomFileName()],
            [$this->randomFileName()],
            [$this->randomFileName()],
        ];
    }

    public function withSubFolderProvider()
    {
        return [
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
        ];
    }

    private function randomFileName()
    {
        return $this->faker()->name.'.'.$this->faker()->fileExtension;
    }

    private function faker()
    {
        return Factory::create();
    }
}
