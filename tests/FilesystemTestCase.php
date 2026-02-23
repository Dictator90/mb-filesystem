<?php

declare(strict_types=1);

use MB\Filesystem\Filesystem;

abstract class FilesystemTestCase extends \PHPUnit\Framework\TestCase
{
    protected string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mb_filesystem_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        if (is_dir($this->tmpDir)) {
            $filesystem->deleteDirectory($this->tmpDir, true);
        }
    }

    protected function fs(): Filesystem
    {
        return new Filesystem($this->tmpDir);
    }
}
