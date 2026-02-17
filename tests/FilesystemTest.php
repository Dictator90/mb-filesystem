<?php

declare(strict_types=1);

use MB\Filesystem\Filesystem;
use MB\Filesystem\Exceptions\FileNotFoundException;
use MB\Filesystem\Exceptions\IOException;

final class FilesystemTest extends \PHPUnit\Framework\TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mb_filesystem_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->deleteDirectoryRecursive($this->tmpDir);
    }

    private function fs(): Filesystem
    {
        return new Filesystem($this->tmpDir);
    }

    public function testPutAndGet(): void
    {
        $fs = $this->fs();
        $path = 'foo.txt';
        $content = 'hello';

        $fs->put($path, $content);

        $this->assertTrue($fs->existsFile($path));
        $this->assertSame($content, $fs->get($path));
    }

    public function testGetNonExistingFileThrows(): void
    {
        $this->expectException(FileNotFoundException::class);

        $fs = $this->fs();
        $fs->get('missing.txt');
    }

    public function testJsonHelpers(): void
    {
        $fs = $this->fs();
        $path = 'config.json';

        $data = ['a' => 1, 'b' => 2];
        $fs->putJson($path, $data);

        $decoded = $fs->getJson($path, true);
        $this->assertSame($data, $decoded);

        $default = ['default' => true];
        $this->assertSame($default, $fs->getJsonOrDefault('missing.json', $default));

        $fs->updateJson($path, static function (array $config): array {
            $config['c'] = 3;
            return $config;
        });

        $updated = $fs->getJson($path, true);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $updated);
    }

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

    public function testAppendAndAtomicWrite(): void
    {
        $fs = $this->fs();
        $path = 'append.txt';

        $fs->append($path, 'a');
        $fs->append($path, 'b');

        $this->assertSame('ab', $fs->get($path));

        $fs->putAtomic($path, 'x');
        $this->assertSame('x', $fs->get($path));
    }

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

    public function testInvalidJsonThrowsIOException(): void
    {
        $fs = $this->fs();
        $path = 'broken.json';

        $fs->put($path, '{invalid json');

        $this->expectException(IOException::class);
        $fs->getJson($path, true);
    }
}

