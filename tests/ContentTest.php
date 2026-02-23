<?php

declare(strict_types=1);

use MB\Filesystem\Exceptions\FileNotFoundException;

final class ContentTest extends FilesystemTestCase
{
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
}
