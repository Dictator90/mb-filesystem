<?php

declare(strict_types=1);

final class ListingTest extends FilesystemTestCase
{
    public function testFilesAndDirectoriesRecursive(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('dir/sub');
        $fs->put('dir/a.txt', 'a');
        $fs->put('dir/sub/b.log', 'b');

        $filesNonRecursive = $fs->files('dir', false);
        $this->assertCount(1, $filesNonRecursive);

        $filesRecursive = $fs->files('dir', true);
        $this->assertCount(2, $filesRecursive);

        $dirsRecursive = $fs->directories('dir', true);
        $this->assertNotEmpty($dirsRecursive);
    }

    public function testFilesWithExtensionAndPattern(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('logs');
        $fs->put('logs/app.log', '1');
        $fs->put('logs/app.debug.log', '2');
        $fs->put('logs/readme.txt', '3');

        $logs = $fs->filesWithExtension('logs', 'log', true);
        $this->assertCount(2, $logs);

        $appLogs = $fs->filesByPattern('logs', 'app*.log', true);
        $this->assertCount(2, $appLogs);
    }
}
