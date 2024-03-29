<?php

namespace VladimirYuldashev\Flysystem\Tests;

use League\Flysystem\Config;
use League\Flysystem\Util;

class CurlFtpAdapterTest extends TestCase
{
    /**
     * @dataProvider filesAndSubfolderFilesProvider
     *
     * @param $filename
     */
    public function testWrite($filename): void
    {
        $contents = $this->faker()->text;

        $this->createResourceDirIfPathHasDir($filename);

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
     * @dataProvider filesAndSubfolderFilesProvider
     *
     * @param $filename
     */
    public function testUpdate($filename): void
    {
        $contents = $this->faker()->text;

        $this->createResourceDirIfPathHasDir($filename);

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
     * @dataProvider filesAndSubfolderFilesProvider
     *
     * @param $filename
     */
    public function testUpdateStream($filename): void
    {
        $contents = $this->faker()->text;

        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $contents);
        rewind($stream);

        $this->createResourceDirIfPathHasDir($filename);

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
    public function testRename($filename): void
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
    public function testCopy($filename): void
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
     * @dataProvider filesAndSubfolderFilesProvider
     *
     * @param $filename
     */
    public function testDelete($filename): void
    {
        $this->createResourceDirIfPathHasDir($filename);

        $this->adapter->write($filename, 'foo', new Config);

        $result = $this->adapter->delete($filename);

        $this->assertTrue($result);
        $this->assertFalse($this->adapter->has($filename));
    }

    public function testCreateAndDeleteDir(): void
    {
        $result = $this->adapter->createDir('foo', new Config);

        $this->assertSame(['type' => 'dir', 'path' => 'foo'], $result);

        $result = $this->adapter->deleteDir('foo');

        $this->assertTrue($result);
    }

    /**
     * @dataProvider filesAndSubfolderFilesProvider
     *
     * @param $filename
     */
    public function testGetSetVisibility($filename): void
    {
        $this->createResourceDirIfPathHasDir($filename);

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
     * @dataProvider filesAndSubfolderFilesProvider
     *
     * @param $name
     */
    public function testRead($name): void
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

    public function testGetMetadata(): void
    {
        $this->assertSame(['type' => 'dir', 'path' => ''], $this->adapter->getMetadata(''));
    }

    /**
     * @dataProvider filesAndSubfolderFilesProvider
     *
     * @param $name
     */
    public function testHas($name): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($name, $contents);

        $this->assertTrue((bool) $this->adapter->has($name));
    }

    public function testGetMimeType(): void
    {
        $this->adapter->write('foo.json', 'bar', new Config);

        $this->assertSame('application/json', $this->adapter->getMimetype('foo.json')['mimetype']);
        $this->assertFalse($this->adapter->getMimetype('bar.json'));
    }

    public function testGetTimestamp(): void
    {
        $this->assertFalse($this->adapter->getTimestamp('foo'));
    }

    /**
     * @dataProvider withSubFolderProvider
     *
     * @param $path
     */
    public function testListContents($path): void
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
    public function testListContentsEmptyPath($path): void
    {
        $this->assertCount(0, $this->adapter->listContents(dirname($path)));
    }

    /**
     * Tests that a FTP server is still in root directory as its working directory
     * after reading a file, especially if this file is in a subfolder.
     *
     * @dataProvider filesAndSubfolderFilesProvider
     *
     * @param $path
     */
    public function testReadAndHasInSequence($path): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($path, $contents);

        $response = $this->adapter->read($path);

        $this->assertSame([
            'type' => 'file',
            'path' => $path,
            'contents' => $contents,
        ], $response);

        $this->assertTrue((bool) $this->adapter->has($path));
    }

    /**
     * Tests that a FTP server is still in root directory as its working directory
     * after writing a file, especially if this file is in a subfolder.
     *
     * @dataProvider filesAndSubfolderFilesProvider
     *
     * @param $path
     */
    public function testWriteAndHasInSequence($path): void
    {
        $contents = $this->faker()->text;

        $this->createResourceDirIfPathHasDir($path);

        $result = $this->adapter->write($path, $contents, new Config);

        $this->assertSame([
            'type' => 'file',
            'path' => $path,
            'contents' => $contents,
            'mimetype' => Util::guessMimeType($this->getResourceAbsolutePath($path), $contents),
        ], $result);

        $this->assertEquals($contents, $this->getResourceContent($path));

        $this->assertTrue((bool) $this->adapter->has($path));
    }

    /**
     * Tests that a FTP server is still in root directory as its working directory
     * after reading a file from a different folder than the file which is checked via has.
     */
    public function testReadAndHasInDifferentFoldersInSequence(): void
    {
        $read_path = $this->faker()->unique()->word.'/'.$this->randomFileName();
        $read_path_contents = $this->faker()->text;
        $this->createResourceFile($read_path, $read_path_contents);

        $has_path = $this->faker()->unique()->word.'/'.$this->randomFileName();
        $has_path_contents = $this->faker()->text;
        $this->createResourceFile($has_path, $has_path_contents);

        $response = $this->adapter->read($read_path);

        $this->assertSame([
            'type' => 'file',
            'path' => $read_path,
            'contents' => $read_path_contents,
        ], $response);

        $this->assertTrue((bool) $this->adapter->has($has_path));
    }

    /**
     * Tests that a FTP server is still in root directory as its working directory
     * after reading a file from a different folder than the file which is checked via has.
     */
    public function testWriteAndHasInDifferentFoldersInSequence(): void
    {
        $write_path = $this->faker()->unique()->word.'/'.$this->randomFileName();
        $write_path_contents = $this->faker()->text;

        $has_path = $this->faker()->unique()->word.'/'.$this->randomFileName();
        $has_path_contents = $this->faker()->text;
        $this->createResourceFile($has_path, $has_path_contents);

        $this->createResourceDirIfPathHasDir($write_path);

        $response = $this->adapter->write($write_path, $write_path_contents, new Config);

        $this->assertSame([
            'type' => 'file',
            'path' => $write_path,
            'contents' => $write_path_contents,
            'mimetype' => Util::guessMimeType($this->getResourceAbsolutePath($write_path), $write_path_contents),
        ], $response);

        $this->assertTrue((bool) $this->adapter->has($has_path));
    }

    public function filesAndSubfolderFilesProvider()
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
            ['test/test.txt'],
            ['тёст/тёст.txt'],
            ['test 1/test.txt'],
            ['test/test 1.txt'],
            ['test  1/test  2.txt'],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
        ];
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
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
            [$this->faker()->word.'/'.$this->randomFileName()],
        ];
    }
}
