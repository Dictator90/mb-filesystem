<?php

declare(strict_types=1);

final class SymlinkTest extends FilesystemTestCase
{
    public function testSymlinkHelpersAndLinkNode(): void
    {
        $fs = $this->fs();

        $targetPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'target.txt';
        $linkPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'link.txt';

        file_put_contents($targetPath, 'hello');

        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlinks are not supported on this platform.');
        }

        if (!@symlink($targetPath, $linkPath)) {
            $this->markTestSkipped('Unable to create symlink (insufficient privileges?).');
        }

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
}
