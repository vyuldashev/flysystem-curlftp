<?php

namespace VladimirYuldashev\Flysystem\Tests;

use League\Flysystem\Util;
use League\Flysystem\Config;

class CurlFtpAdapterTest extends TestCase
{
    /**
     * @dataProvider filesProvider
     *
     * @param $filename
     */
    public function testWrite($filename)
    {
        $contents = $this->faker()->text;

        $result = $this->adapter->write($filename, $contents, new Config);

        $this->assertSame([
            'type' => 'file',
            'path' => $filename,
            'contents' => $contents,
            'mimetype' => Util::guessMimeType($this->getResourceAbsolutePath($filename), $contents),
        ], $result);

        $this->assertEquals($contents, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $filename
     */
    public function testUpdate($filename)
    {
        $contents = $this->faker()->text;

        $this->adapter->write($filename, $contents, new Config);
        $this->assertEquals($contents, $this->getResourceContent($filename));

        $newContents = $this->faker()->text;
        $result = $this->adapter->update($filename, $newContents, new Config);

        $this->assertSame([
            'type' => 'file',
            'path' => $filename,
            'contents' => $newContents,
            'mimetype' => Util::guessMimeType($this->getResourceAbsolutePath($filename), $contents),
        ], $result);

        $this->assertNotEquals($contents, $this->getResourceContent($filename));
        $this->assertEquals($newContents, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $filename
     */
    public function testUpdateStream($filename)
    {
        $contents = $this->faker()->text;

        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $contents);
        rewind($stream);

        $this->adapter->writeStream($filename, $stream, new Config);
        $this->assertEquals($contents, $this->getResourceContent($filename));

        $newContents = $this->faker()->text;

        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $newContents);
        rewind($stream);

        $this->adapter->updateStream($filename, $stream, new Config);

        $this->assertNotEquals($contents, $this->getResourceContent($filename));
        $this->assertEquals($newContents, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $filename
     */
    public function testRename($filename)
    {
        $this->adapter->write($filename, 'foo', new Config);

        $newFilename = $this->randomFileName();

        $result = $this->adapter->rename($filename, $newFilename);

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->has($filename));
        $this->assertNotFalse($this->adapter->has($newFilename));
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $filename
     */
    public function testCopy($filename)
    {
        $this->adapter->write($filename, 'foo', new Config);

        $result = $this->adapter->copy($filename, 'bar');

        $this->assertTrue($result);
        $this->assertNotFalse($this->adapter->has($filename));
        $this->assertNotFalse($this->adapter->has('bar'));
        $this->assertEquals($this->adapter->read($filename)['contents'], $this->adapter->read('bar')['contents']);

        $this->assertFalse($this->adapter->copy('foo-bar', 'bar-foo'));
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $filename
     */
    public function testDelete($filename)
    {
        $this->adapter->write($filename, 'foo', new Config);

        $result = $this->adapter->delete($filename);

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->has($filename));
    }

    public function testCreateAndDeleteDir()
    {
        $result = $this->adapter->createDir('foo', new Config);

        $this->assertSame(['type' => 'dir', 'path' => 'foo'], $result);

        $result = $this->adapter->deleteDir('foo');

        $this->assertTrue($result);
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $filename
     */
    public function testGetSetVisibility($filename)
    {
        $this->adapter->write($filename, 'foo', new Config);

        $result = $this->adapter->setVisibility($filename, 'public');

        $this->assertNotFalse($result);
        $this->assertSame('public', $result['visibility']);
        $this->assertSame('public', $this->adapter->getVisibility($filename)['visibility']);

        $result = $this->adapter->setVisibility($filename, 'private');

        $this->assertNotFalse($result);
        $this->assertSame('private', $result['visibility']);
        $this->assertSame('private', $this->adapter->getVisibility($filename)['visibility']);

        $this->assertFalse($this->adapter->setVisibility('bar', 'public'));
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $name
     */
    public function testRead($name)
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($name, $contents);

        $response = $this->adapter->read($name);

        $this->assertSame([
            'type' => 'file',
            'path' => $name,
            'contents' => $contents,
        ], $response);
    }

    public function testGetMetadata()
    {
        $this->assertSame(['type' => 'dir', 'path' => ''], $this->adapter->getMetadata(''));
    }

    /**
     * @dataProvider filesProvider
     *
     * @param $name
     */
    public function testHas($name)
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($name, $contents);

        $this->assertTrue((bool) $this->adapter->has($name));
    }

    /**
     * @dataProvider withSubFolderProvider
     *
     * @param $path
     */
    public function testHasInSubFolder($path)
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($path, $contents);

        $this->assertTrue((bool) $this->adapter->has($path));
    }

    public function testGetMimeType()
    {
        $this->adapter->write('foo.json', 'bar', new Config);

        $this->assertSame('application/json', $this->adapter->getMimetype('foo.json')['mimetype']);
        $this->assertFalse($this->adapter->getMimetype('bar.json'));
    }

    public function testGetTimestamp()
    {
        $this->assertFalse($this->adapter->getTimestamp('foo'));
    }

    /**
     * @dataProvider withSubFolderProvider
     *
     * @param $path
     */
    public function testListContents($path)
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($path, $contents);

        $this->assertCount(1, $this->adapter->listContents(dirname($path)));
    }

    /**
     * @dataProvider withSubFolderProvider
     *
     * @param $path
     */
    public function testListContentsEmptyPath($path)
    {
        $this->assertCount(0, $this->adapter->listContents(dirname($path)));
    }

    public function filesProvider()
    {
        return [
            ['test.txt'],
            ['..test.txt'],
            ['test 1.txt'],
            ['test  2.txt'],
            ['тест.txt'],
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
            ['test/test.txt'],
            ['тёст/тёст.txt'],
            ['test 1/test.txt'],
            ['test/test 1.txt'],
            ['test  1/test  2.txt'],
            [$this->faker()->word . '/' . $this->randomFileName()],
            [$this->faker()->word . '/' . $this->randomFileName()],
            [$this->faker()->word . '/' . $this->randomFileName()],
            [$this->faker()->word . '/' . $this->randomFileName()],
            [$this->faker()->word . '/' . $this->randomFileName()],
        ];
    }
}
