<?php

namespace VladimirYuldashev\Flysystem\Tests;

use Faker\Factory;
use PHPUnit_Framework_TestCase;

abstract class TestCase extends PHPUnit_Framework_TestCase
{
    protected $root = '';

    public function tearDown()
    {
        unset($this->adapter);
        $this->clearResources();
    }

    protected function getResourcesPath()
    {
        return __DIR__.'/resources/';
    }

    protected function getResourceContent($path)
    {
        return file_get_contents($this->getResourceAbsolutePath($path));
    }

    protected function getResourceAbsolutePath($path)
    {
        return implode('/', array_filter([
            rtrim($this->getResourcesPath(), '/'),
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

    protected function faker()
    {
        return Factory::create();
    }

    protected function clearResources()
    {
        exec('rm -rf '.escapeshellarg($this->getResourcesPath()).'*');
        exec('rm -rf '.escapeshellarg($this->getResourcesPath()).'.* 2>/dev/null');
        clearstatcache();
    }
}
