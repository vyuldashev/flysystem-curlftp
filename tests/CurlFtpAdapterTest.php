<?php

declare(strict_types=1);

namespace VladimirYuldashev\Flysystem\Tests;

use League\Flysystem\Config;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;

class CurlFtpAdapterTest extends TestCase
{
    /**
     * @dataProvider filesAndSubfolderFilesProvider
     */
    public function testWrite(string $filename): void
    {
        $contents = $this->faker()->text;

        $this->createResourceDirIfPathHasDir($filename);

        $this->adapter->write($filename, $contents, self::publicConfig());

        $this->assertTrue($this->adapter->fileExists($filename));

        $this->assertEquals($contents, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesAndSubfolderFilesProvider
     */
    public function testUpdate(string $filename): void
    {
        $contents = $this->faker()->text;

        $this->createResourceDirIfPathHasDir($filename);

        $this->adapter->write($filename, $contents, self::publicConfig());
        $this->assertEquals($contents, $this->getResourceContent($filename));

        $newContents = $this->faker()->text;
        $this->adapter->write($filename, $newContents, self::publicConfig());

        $this->assertNotEquals($contents, $this->getResourceContent($filename));
        $this->assertEquals($newContents, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesAndSubfolderFilesProvider
     */
    public function testUpdateStream(string $filename): void
    {
        $contents = $this->faker()->text;

        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $contents);
        rewind($stream);

        $this->createResourceDirIfPathHasDir($filename);

        $this->adapter->writeStream($filename, $stream, self::publicConfig());
        $this->assertEquals($contents, $this->getResourceContent($filename));

        $newContents = $this->faker()->text;

        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $newContents);
        rewind($stream);

        $this->adapter->writeStream($filename, $stream, self::publicConfig());

        $this->assertNotEquals($contents, $this->getResourceContent($filename));
        $this->assertEquals($newContents, $this->getResourceContent($filename));
    }

    /**
     * @dataProvider filesProvider
     */
    public function testRename(string $filename): void
    {
        $this->adapter->write($filename, 'foo', new Config);

        $newFilename = $this->randomFileName();

        $this->adapter->move($filename, $newFilename, new Config);

        $this->assertFalse($this->adapter->fileExists($filename));
        $this->assertNotFalse($this->adapter->fileExists($newFilename));
    }

    /**
     * @dataProvider filesProvider
     */
    public function testCopy(string $filename): void
    {
        $this->adapter->write($filename, 'foo', new Config);

        $this->adapter->copy($filename, 'bar', new Config);

        $this->assertNotFalse($this->adapter->fileExists($filename));
        $this->assertNotFalse($this->adapter->fileExists('bar'));
        $this->assertEquals($this->adapter->read($filename), $this->adapter->read('bar'));
    }

    public function testCopyFails(): void
    {
        $this->expectException(UnableToCopyFile::class);
        $this->adapter->copy('foo-bar', 'bar-foo', new Config);
    }

    /**
     * @dataProvider filesAndSubfolderFilesProvider
     */
    public function testDelete(string $filename): void
    {
        $this->createResourceDirIfPathHasDir($filename);

        $this->adapter->write($filename, 'foo', new Config);

        $this->adapter->delete($filename);

        $this->assertFalse($this->adapter->fileExists($filename));
    }

    public function testCreateAndDeleteDir(): void
    {
        $this->assertFalse($this->adapter->directoryExists('foo'));

        $this->adapter->createDirectory('foo', self::publicConfig());

        $this->assertTrue($this->adapter->directoryExists('foo'));

        $this->adapter->deleteDirectory('foo');

        $this->assertFalse($this->adapter->directoryExists('foo'));
    }

    /**
     * @dataProvider filesAndSubfolderFilesProvider
     */
    public function testGetSetVisibility(string $filename): void
    {
        $this->createResourceDirIfPathHasDir($filename);

        $this->adapter->write($filename, 'foo', new Config);

        $this->adapter->setVisibility($filename, 'public');

        $this->assertSame('public', $this->adapter->visibility($filename)->visibility());

        $this->adapter->setVisibility($filename, 'private');

        $this->assertSame('private', $this->adapter->visibility($filename)->visibility());
    }

    public function testGetSetVisibilityFails(): void
    {
        $this->expectException(UnableToSetVisibility::class);
        $this->adapter->setVisibility('bar', 'public');
    }

    /**
     * @dataProvider filesAndSubfolderFilesProvider
     */
    public function testRead(string $name): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($name, $contents);

        $response = $this->adapter->read($name);

        $this->assertSame($contents, $response);
    }

    /**
     * @dataProvider filesAndSubfolderFilesProvider
     */
    public function testFileExists(string $name): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($name, $contents);

        $this->assertTrue((bool) $this->adapter->fileExists($name));
    }

    public function testMimeType(): void
    {
        $this->adapter->write('foo.json', 'bar', new Config);

        $this->assertSame('application/json', $this->adapter->mimeType('foo.json')->mimeType());
    }

    public function testMimeTypeFaileNotExist(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->adapter->mimeType('bar.json');
    }

    public function testLastModifiedFailed(): void
    {
        $this->expectException(UnableToRetrieveMetadata::class);
        $this->adapter->lastModified('foo');
    }

    public function testLastModified(): void
    {
        $this->adapter->write('foo.json.lastModified', 'bar', new Config);
        $lastModified = $this->adapter->lastModified('foo.json.lastModified')->lastModified();
        $this->assertGreaterThan(strtotime("yesterday 0:00"), $lastModified);
    }

    /**
     * @dataProvider withSubFolderProvider
     */
    public function testListContents(string $path): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($path, $contents);

        $this->assertCount(1, iterator_to_array($this->adapter->listContents(dirname($path), false), false));
    }

    /**
     * @dataProvider withSubFolderProvider
     */
    public function testListContentsEmptyPath(string $path): void
    {
        $this->assertCount(0, iterator_to_array($this->adapter->listContents(dirname($path), false), false));
    }

    /**
     * Tests that a FTP server is still in root directory as its working directory
     * after reading a file, especially if this file is in a subfolder.
     *
     * @dataProvider filesAndSubfolderFilesProvider
     */
    public function testReadAndHasInSequence(string $path): void
    {
        $contents = $this->faker()->text;
        $this->createResourceFile($path, $contents);

        $response = $this->adapter->read($path);

        $this->assertSame($contents, $response);

        $this->assertTrue((bool) $this->adapter->fileExists($path));
    }

    /**
     * Tests that a FTP server is still in root directory as its working directory
     * after writing a file, especially if this file is in a subfolder.
     *
     * @dataProvider filesAndSubfolderFilesProvider
     */
    public function testWriteAndHasInSequence(string $path): void
    {
        $contents = $this->faker()->text;

        $this->createResourceDirIfPathHasDir($path);

        $this->adapter->write($path, $contents, self::publicConfig());

        $this->assertEquals($contents, $this->getResourceContent($path));

        $this->assertTrue((bool) $this->adapter->fileExists($path));
    }

    /**
     * Tests that a FTP server is still in root directory as its working directory
     * after reading a file from a different folder than the file which is checked via has.
     */
    public function testReadAndHasInDifferentFoldersInSequence(): void
    {
        $read_path = $this->faker()->unique()->word . '/' . $this->randomFileName();
        $read_path_contents = $this->faker()->text;
        $this->createResourceFile($read_path, $read_path_contents);

        $has_path = $this->faker()->unique()->word . '/' . $this->randomFileName();
        $has_path_contents = $this->faker()->text;
        $this->createResourceFile($has_path, $has_path_contents);

        $response = $this->adapter->read($read_path);

        $this->assertSame($read_path_contents, $response);

        $this->assertTrue((bool) $this->adapter->fileExists($has_path));
    }

    /**
     * Tests that a FTP server is still in root directory as its working directory
     * after reading a file from a different folder than the file which is checked via has.
     */
    public function testWriteAndHasInDifferentFoldersInSequence(): void
    {
        $write_path = $this->faker()->unique()->word . '/' . $this->randomFileName();
        $write_path_contents = $this->faker()->text;

        $has_path = $this->faker()->unique()->word . '/' . $this->randomFileName();
        $has_path_contents = $this->faker()->text;
        $this->createResourceFile($has_path, $has_path_contents);

        $this->createResourceDirIfPathHasDir($write_path);

        $this->adapter->write($write_path, $write_path_contents, new Config);

        $this->assertTrue((bool) $this->adapter->fileExists($has_path));
    }

    public static function filesAndSubfolderFilesProvider(): array
    {
        return [
            ['test.txt'],
            ['..test.txt'],
            ['test 1.txt'],
            ['test  2.txt'],
            ['тест.txt'],
            [self::randomFileName()],
            [self::randomFileName()],
            [self::randomFileName()],
            [self::randomFileName()],
            [self::randomFileName()],
            ['test/test.txt'],
            ['тёст/тёст.txt'],
            ['test 1/test.txt'],
            ['test/test 1.txt'],
            ['test  1/test  2.txt'],
            [self::faker()->word . '/' . self::randomFileName()],
            [self::faker()->word . '/' . self::randomFileName()],
            [self::faker()->word . '/' . self::randomFileName()],
            [self::faker()->word . '/' . self::randomFileName()],
            [self::faker()->word . '/' . self::randomFileName()],
        ];
    }

    public static function filesProvider(): array
    {
        return [
            ['test.txt'],
            ['..test.txt'],
            ['test 1.txt'],
            ['test  2.txt'],
            ['тест.txt'],
            [self::randomFileName()],
            [self::randomFileName()],
            [self::randomFileName()],
            [self::randomFileName()],
            [self::randomFileName()],
        ];
    }

    public static function withSubFolderProvider(): array
    {
        return [
            ['test/test.txt'],
            ['тёст/тёст.txt'],
            ['test 1/test.txt'],
            ['test/test 1.txt'],
            ['test  1/test  2.txt'],
            [self::faker()->word . '/' . self::randomFileName()],
            [self::faker()->word . '/' . self::randomFileName()],
            [self::faker()->word . '/' . self::randomFileName()],
            [self::faker()->word . '/' . self::randomFileName()],
            [self::faker()->word . '/' . self::randomFileName()],
        ];
    }
}
