<?php

declare(strict_types=1);

final class DeleteTest extends FilesystemTestCase
{
    public function testDeleteDirectoryNonRecursive(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('empty');
        $this->assertTrue($fs->existsDirectory('empty'));
        $fs->deleteDirectory('empty', false);
        $this->assertFalse($fs->exists('empty'));
    }

    public function testDeleteDirectoryRecursive(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('tree/a');
        $fs->put('tree/f.txt', 'x');
        $fs->put('tree/a/b.txt', 'y');
        $fs->deleteDirectory('tree', true);
        $this->assertFalse($fs->exists('tree'));
    }
}
