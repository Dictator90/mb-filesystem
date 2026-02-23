<?php

declare(strict_types=1);

use MB\Filesystem\Exceptions\FileNotFoundException;
use MB\Filesystem\Nodes\File;

final class FindTest extends FilesystemTestCase
{
    public function testFindPathsByName(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('a');
        $fs->makeDirectory('a/b');
        $fs->put('a/component.php', '');
        $fs->put('a/b/component.php', '');

        $paths = $fs->findPaths('a', ['name' => 'component.php']);

        $this->assertCount(2, $paths);
        $this->assertTrue(
            str_contains($paths[0], 'component.php') && str_contains($paths[1], 'component.php')
        );
    }

    public function testFindPathsByNamePattern(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('tpl');
        $fs->put('tpl/header.php', '');
        $fs->put('tpl/footer.php', '');
        $fs->put('tpl/readme.txt', '');

        $paths = $fs->findPaths('tpl', ['name_pattern' => '*.php', 'type' => 'file']);

        $this->assertCount(2, $paths);
        $exts = array_unique(array_map(fn ($p) => pathinfo($p, PATHINFO_EXTENSION), $paths));
        $this->assertSame(['php'], $exts);
    }

    public function testFindPathsByType(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('root');
        $fs->makeDirectory('root/sub');
        $fs->put('root/file.php', '');

        $dirs = $fs->findPaths('root', ['type' => 'dir']);
        $files = $fs->findPaths('root', ['type' => 'file']);

        $this->assertGreaterThanOrEqual(1, count($dirs));
        $this->assertContains($this->tmpDir . DIRECTORY_SEPARATOR . 'root' . DIRECTORY_SEPARATOR . 'sub', $dirs);
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('file.php', $files[0]);
    }

    public function testFindPathsMaxDepth(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('d1');
        $fs->makeDirectory('d1/d2');
        $fs->put('d1/f1.php', '');
        $fs->put('d1/d2/f2.php', '');

        $depth0 = $fs->findPaths('d1', ['name_pattern' => '*.php', 'type' => 'file', 'max_depth' => 0]);
        $this->assertCount(0, $depth0);

        $depth1 = $fs->findPaths('d1', ['name_pattern' => '*.php', 'type' => 'file', 'max_depth' => 1]);
        $this->assertCount(1, $depth1);
        $this->assertStringEndsWith('f1.php', $depth1[0]);

        $depth2 = $fs->findPaths('d1', ['name_pattern' => '*.php', 'type' => 'file', 'max_depth' => 2]);
        $this->assertCount(2, $depth2);
    }

    public function testFindReturnsNodes(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('n');
        $fs->put('n/f.php', '');

        $nodes = $fs->find('n', ['type' => 'file']);

        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(File::class, $nodes[0]);
        $this->assertStringContainsString('f.php', $nodes[0]->path);
    }

    public function testFindPathsMatchFindNodePaths(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('p');
        $fs->put('p/a.php', '');
        $fs->put('p/b.php', '');

        $paths = $fs->findPaths('p', ['name_pattern' => '*.php', 'type' => 'file']);
        $nodes = $fs->find('p', ['name_pattern' => '*.php', 'type' => 'file']);

        $this->assertCount(2, $paths);
        $this->assertCount(2, $nodes);
        $nodePaths = array_map(fn ($n) => $n->path, $nodes);
        sort($paths);
        sort($nodePaths);
        $this->assertSame($paths, $nodePaths);
    }

    public function testFindPathsNonexistentDirectoryThrows(): void
    {
        $this->expectException(FileNotFoundException::class);

        $fs = $this->fs();
        $fs->findPaths('nonexistent', []);
    }
}
