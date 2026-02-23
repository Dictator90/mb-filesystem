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
        $filesystem->deleteDirectory($this->tmpDir, true);
    }

    private function fs(): Filesystem
    {
        return new Filesystem($this->tmpDir);
    }

    public function testPutAndGetContent(): void
    {
        $fs = $this->fs();
        $path = 'foo.txt';
        $content = 'hello';

        $fs->put($path, $content);

        $this->assertTrue($fs->existsFile($path));
        $this->assertSame($content, $fs->content($path));
    }

    public function testContentNonExistingFileThrows(): void
    {
        $this->expectException(FileNotFoundException::class);

        $fs = $this->fs();
        $fs->content('missing.txt');
    }

    public function testJsonHelpers(): void
    {
        $fs = $this->fs();
        $path = 'config.json';

        $data = ['a' => 1, 'b' => 2];
        $fs->putJson($path, $data);

        $decoded = $fs->json($path, true);
        $this->assertSame($data, $decoded);

        $default = ['default' => true];
        $this->assertSame($default, $fs->json('missing.json', true, $default));

        $fs->updateJson($path, static function (array $config): array {
            $config['c'] = 3;
            return $config;
        });

        $updated = $fs->json($path, true);
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

        $this->assertSame('ab', $fs->content($path));

        $fs->putAtomic($path, 'x');
        $this->assertSame('x', $fs->content($path));
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
        $fs->json($path, true);
    }

    public function testContentAndUpdateContent(): void
    {
        $fs = $this->fs();
        $path = 'log.txt';

        $this->assertSame('', $fs->content($path, ''));
        $this->assertSame('fallback', $fs->content('missing.txt', 'fallback'));

        $fs->updateContent($path, static fn (string $c) => $c . "line1\n");
        $fs->updateContent($path, static fn (string $c) => $c . "line2\n");

        $this->assertSame("line1\nline2\n", $fs->content($path));
    }

    public function testContentMissingFileThrowsWhenNoDefault(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->fs()->content('missing.txt');
    }

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

    public function testUpdateContentAcceptsFileObject(): void
    {
        $fs = $this->fs();
        $fs->put('log.txt', 'a');
        $file = $fs->file('log.txt');
        $fs->updateContent($file, static fn (string $c) => $c . 'b');
        $this->assertSame('ab', $fs->content('log.txt'));
    }

    public function testUpdateContentAtomicTrueOverwritesCorrectly(): void
    {
        $fs = $this->fs();
        $fs->put('atomic.txt', 'initial');
        $fs->updateContent('atomic.txt', static fn (string $c) => $c . '-updated', true);
        $this->assertSame('initial-updated', $fs->content('atomic.txt'));
    }

    public function testUpdateContentAtomicFalseOverwritesCorrectly(): void
    {
        $fs = $this->fs();
        $fs->put('nonatomic.txt', 'initial');
        $fs->updateContent('nonatomic.txt', static fn (string $c) => $c . '-updated', false);
        $this->assertSame('initial-updated', $fs->content('nonatomic.txt'));
    }

    public function testFileUpdateAcceptsAtomicParameter(): void
    {
        $fs = $this->fs();
        $fs->put('node.txt', 'x');
        $file = $fs->file('node.txt');
        $file->update(static fn (string $c) => $c . 'y', false);
        $this->assertSame('xy', $fs->content('node.txt'));
    }

    public function testFileNodeContentAndLinesAndUpdate(): void
    {
        $fs = $this->fs();
        $fs->put('lines.txt', "line1\nline2\nline3");
        $file = $fs->file('lines.txt');

        $this->assertSame("line1\nline2\nline3", $file->content());

        $lines = iterator_to_array($file->lines());
        $this->assertCount(3, $lines);
        $this->assertSame("line1\n", $lines[0]);
        $this->assertSame("line2\n", $lines[1]);
        $this->assertSame('line3', $lines[2]);

        $file->update(static fn (string $c) => $c . "\nline4");
        $this->assertStringEndsWith('line4', $fs->content('lines.txt'));

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

    public function testFileNodeLineByNumber(): void
    {
        $fs = $this->fs();
        $fs->put('numbered.txt', "first\nsecond\nthird");
        $file = $fs->file('numbered.txt');

        $this->assertSame("first\n", $file->line(1));
        $this->assertSame("second\n", $file->line(2));
        $this->assertSame('third', $file->line(3));

        $this->expectException(IOException::class);
        $this->expectExceptionMessage('Line 5 does not exist');
        $file->line(5);
    }

    public function testFileNodeLineRequiresPositiveNumber(): void
    {
        $fs = $this->fs();
        $fs->put('one.txt', 'x');
        $file = $fs->file('one.txt');

        $this->expectException(IOException::class);
        $this->expectExceptionMessage('Line number must be >= 1');
        $file->line(0);
    }

    public function testFileNodeWriteAndDelete(): void
    {
        $fs = $this->fs();
        $fs->put('writable.txt', 'old');
        $file = $fs->file('writable.txt');
        $file->write('new');
        $this->assertSame('new', $file->content());
        $file->delete();
        $this->assertFalse($fs->exists('writable.txt'));
    }

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

    public function testSymlinkHelpersAndLinkNode(): void
    {
        $fs = $this->fs();

        // Create target file and symlink (skip on platforms without symlink support)
        $targetPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'target.txt';
        $linkPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'link.txt';

        file_put_contents($targetPath, 'hello');

        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlinks are not supported on this platform.');
        }

        // @ is to avoid warnings on Windows without privileges
        if (!@symlink($targetPath, $linkPath)) {
            $this->markTestSkipped('Unable to create symlink (insufficient privileges?).');
        }

        $relativeTarget = 'target.txt';
        $relativeLink = 'link.txt';

        $this->assertTrue($fs->isLink($relativeLink));

        $linkNode = $fs->link($relativeLink);
        $this->assertInstanceOf(\MB\Filesystem\Nodes\Link::class, $linkNode);
        $this->assertFalse($linkNode->isBroken());

        $resolved = $linkNode->resolve();
        $this->assertInstanceOf(\MB\Filesystem\Nodes\File::class, $resolved);
        $this->assertSame('hello', $fs->content($resolved->path));

        $links = $fs->links('.', false);
        $this->assertNotEmpty($links);
    }

    public function testCreateSymlink(): void
    {
        $fs = $this->fs();
        $fs->put('target.txt', 'content');

        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlinks are not supported on this platform.');
        }

        try {
            $fs->createSymlink('target.txt', 'link.txt');
        } catch (\MB\Filesystem\Exceptions\IOException $e) {
            $this->markTestSkipped('Unable to create symlink (e.g. insufficient privileges on Windows): ' . $e->getMessage());
        }

        $this->assertTrue($fs->isLink('link.txt'));
        $link = $fs->link('link.txt');
        $this->assertFalse($link->isBroken());
        $resolved = $link->resolve();
        $this->assertInstanceOf(\MB\Filesystem\Nodes\File::class, $resolved);
        $this->assertSame('content', $fs->content('link.txt'));
    }

    public function testCreateSymlinkFailsWhenLinkPathExists(): void
    {
        $fs = $this->fs();
        $fs->put('existing.txt', 'x');

        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlinks are not supported on this platform.');
        }

        $this->expectException(\MB\Filesystem\Exceptions\IOException::class);
        $this->expectExceptionMessage('already exists');
        $fs->createSymlink('target.txt', 'existing.txt');
    }

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

