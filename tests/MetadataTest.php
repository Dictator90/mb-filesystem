<?php

declare(strict_types=1);

final class MetadataTest extends FilesystemTestCase
{
    public function testSizeAndLastModified(): void
    {
        $fs = $this->fs();
        $path = 'size.txt';

        $fs->put($path, '12345');

        $this->assertSame(5, $fs->size($path));

        $mtime = $fs->lastModified($path);
        $this->assertIsInt($mtime);
        $this->assertGreaterThan(0, $mtime);
    }

    public function testChmodAndTouch(): void
    {
        $fs = $this->fs();
        $fs->put('perms.txt', 'x');
        $fs->chmod('perms.txt', 0644);
        $file = $fs->file('perms.txt');
        $this->assertIsInt($file->mode);

        $past = time() - 100;
        $fs->touch('perms.txt', $past);
        $this->assertSame($past, $fs->lastModified('perms.txt'));
    }
}
