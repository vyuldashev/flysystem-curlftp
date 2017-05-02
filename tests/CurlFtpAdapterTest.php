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
     *
     * @param $name
     */
    public function testRead($name)
    {
        $data = $this->faker()->text;
        $this->createResourceFile($name, $data);

        $response = $this->adapter->read($name);
        $this->assertEquals($data, $response['contents']);
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $filename
     */
    public function testWrite($filename)
    {
        $data = $this->faker()->text;

        $this->adapter->write($filename, $data, new Config);
        $this->assertEquals($data, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $name
     */
    public function testHas($name)
    {
        $data = $this->faker()->text;
        $this->createResourceFile($name, $data);

        $this->assertTrue((bool) $this->adapter->has($name));
    }

    /**
     * @dataProvider withSubFolderProvider
     *
     * @param $path
     */
    public function testHasInSubFolder($path)
    {
        $data = $this->faker()->text;
        $this->createResourceFile($path, $data);

        $this->assertTrue((bool) $this->adapter->has($path));
    }

    /**
     * @dataProvider withSubFolderProvider
     *
     * @param $path
     */
    public function testListContents($path)
    {
        $data = $this->faker()->text;
        $this->createResourceFile($path, $data);

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
