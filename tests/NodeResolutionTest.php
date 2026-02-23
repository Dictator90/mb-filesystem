<?php

declare(strict_types=1);

use MB\Filesystem\Exceptions\FileNotFoundException;

final class NodeResolutionTest extends FilesystemTestCase
{
    public function testFileReturnsFileNode(): void
    {
        $fs = $this->fs();
        $fs->put('info.txt', 'hello');

        $file = $fs->file('info.txt');

        $this->assertSame(5, $file->size);
        $this->assertSame('info.txt', $file->filename);
        $this->assertSame('info', $file->basename);
        $this->assertSame('txt', $file->extension);
        $this->assertIsInt($file->lastModified);
        $this->assertTrue($file->readable);
    }

    public function testFileThrowsWhenNotAFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        $fs = $this->fs();
        $fs->makeDirectory('dir');
        $fs->file('dir');
    }

    public function testDirectoryReturnsDirectoryNode(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('data');

        $dir = $fs->directory('data');

        $this->assertNotEmpty($dir->path);
        $this->assertIsInt($dir->lastModified);
        $this->assertTrue($dir->readable);
    }

    public function testDirectoryThrowsWhenNotADirectory(): void
    {
        $this->expectException(FileNotFoundException::class);
        $fs = $this->fs();
        $fs->put('file.txt', 'x');
        $fs->directory('file.txt');
    }

    public function testGetReturnsFileOrDirectoryNodes(): void
    {
        $fs = $this->fs();
        $fs->put('node.txt', 'x');
        $fs->makeDirectory('dir');

        $fileNode = $fs->get('node.txt');
        $dirNode = $fs->get('dir');

        $this->assertInstanceOf(\MB\Filesystem\Nodes\File::class, $fileNode);
        $this->assertInstanceOf(\MB\Filesystem\Nodes\Directory::class, $dirNode);
    }
}
