<?php

declare(strict_types=1);

final class DirectoryNodeTest extends FilesystemTestCase
{
    public function testDirectoryNodeDeleteAndFiles(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('dir');
        $fs->put('dir/a.txt', 'a');
        $dir = $fs->directory('dir');
        $this->assertCount(1, $dir->files());
        $dir->delete(true);
        $this->assertFalse($fs->exists('dir'));
    }
}
