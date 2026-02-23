<?php

declare(strict_types=1);

use MB\Filesystem\Exceptions\FileNotFoundException;

final class MoveRenameRealPathTest extends FilesystemTestCase
{
    public function testMoveDirectory(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('olddir');
        $fs->put('olddir/file.txt', 'data');
        $fs->move('olddir', 'newdir');
        $this->assertFalse($fs->exists('olddir'));
        $this->assertTrue($fs->exists('newdir'));
        $this->assertSame('data', $fs->content('newdir/file.txt'));
    }

    public function testRenameFile(): void
    {
        $fs = $this->fs();
        $fs->put('old.txt', 'text');
        $fs->rename('old.txt', 'new.txt');
        $this->assertFalse($fs->exists('old.txt'));
        $this->assertSame('text', $fs->content('new.txt'));
    }

    public function testRenameDirectory(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('oldname');
        $fs->put('oldname/a.txt', 'a');
        $fs->rename('oldname', 'newname');
        $this->assertFalse($fs->exists('oldname'));
        $this->assertSame('a', $fs->content('newname/a.txt'));
    }

    public function testRealPath(): void
    {
        $fs = $this->fs();
        $fs->put('file.txt', 'x');
        $real = $fs->realPath('file.txt');
        $this->assertNotEmpty($real);
        $this->assertStringEndsWith('file.txt', $real);
    }

    public function testRealPathMissingThrows(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->fs()->realPath('nonexistent.txt');
    }
}
