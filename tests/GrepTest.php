<?php

declare(strict_types=1);

use MB\Filesystem\Exceptions\FileNotFoundException;
use MB\Filesystem\Nodes\File;

final class GrepTest extends FilesystemTestCase
{
    public function testGrepPathsReturnsMatchingPaths(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('project');
        $needle = 'FOO_BAR_TOKEN';
        $fs->put('project/match.php', "<?php\n{$needle};\n");
        $fs->put('project/nope.php', "<?php\n// no token\n");
        $fs->put('project/other.txt', "text with {$needle}");

        $paths = $fs->grepPaths('project', $needle);

        $this->assertIsArray($paths);
        $this->assertContains($this->tmpDir . DIRECTORY_SEPARATOR . 'project' . DIRECTORY_SEPARATOR . 'match.php', $paths);
        $this->assertNotContains($this->tmpDir . DIRECTORY_SEPARATOR . 'project' . DIRECTORY_SEPARATOR . 'nope.php', $paths);
        $this->assertCount(1, $paths, 'Only PHP files by default');
    }

    public function testGrepPathsWithExtensions(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('project');
        $needle = 'FOO_BAR_TOKEN';
        $fs->put('project/a.php', $needle);
        $fs->put('project/b.txt', $needle);
        $fs->put('project/c.js', $needle);

        $paths = $fs->grepPaths('project', $needle, ['extensions' => ['php', 'js']]);

        $this->assertCount(2, $paths);
        $pathsBasenames = array_map('basename', $paths);
        $this->assertContains('a.php', $pathsBasenames);
        $this->assertContains('c.js', $pathsBasenames);
        $this->assertNotContains('b.txt', $pathsBasenames);
    }

    public function testGrepPathsCaseSensitive(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('project');
        $fs->put('project/upper.php', 'FOO_BAR_TOKEN');
        $fs->put('project/lower.php', 'foo_bar_token');

        $paths = $fs->grepPaths('project', 'FOO_BAR_TOKEN', ['case_sensitive' => true]);
        $this->assertCount(1, $paths);
        $this->assertStringEndsWith('upper.php', $paths[0]);

        $paths2 = $fs->grepPaths('project', 'foo_bar_token', ['case_sensitive' => false]);
        $this->assertCount(2, $paths2);
    }

    public function testGrepPathsRegex(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('project');
        $fs->put('project/a.php', 'FOO_x_TOKEN');
        $fs->put('project/b.php', 'FOO_y_TOKEN');
        $fs->put('project/c.php', 'no match');

        $paths = $fs->grepPaths('project', '/FOO_.*_TOKEN/', ['regex' => true]);
        $this->assertCount(2, $paths);
    }

    public function testGrepReturnsFileNodes(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('project');
        $needle = 'FOO_BAR_TOKEN';
        $fs->put('project/match.php', "<?php\n{$needle};\n");

        $files = $fs->grep('project', $needle);

        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $this->assertInstanceOf(File::class, $files[0]);
        $this->assertStringContainsString('match.php', $files[0]->path);
        $this->assertStringContainsString($needle, $files[0]->content());
    }

    public function testGrepPathsMatchGrepPathsResult(): void
    {
        $fs = $this->fs();
        $fs->makeDirectory('project');
        $fs->put('project/one.php', 'TOKEN');
        $fs->put('project/two.php', 'TOKEN');

        $paths = $fs->grepPaths('project', 'TOKEN');
        $files = $fs->grep('project', 'TOKEN');

        $this->assertCount(2, $paths);
        $this->assertCount(2, $files);
        $filePaths = array_map(fn (File $f) => $f->path, $files);
        sort($paths);
        sort($filePaths);
        $this->assertSame($paths, $filePaths);
    }

    public function testGrepPathsNonexistentDirectoryThrows(): void
    {
        $this->expectException(FileNotFoundException::class);

        $fs = $this->fs();
        $fs->grepPaths('nonexistent', 'x');
    }

    public function testGrepInvalidRegexThrows(): void
    {
        $this->expectException(\MB\Filesystem\Exceptions\IOException::class);

        $fs = $this->fs();
        $fs->makeDirectory('r');
        $fs->grepPaths('r', '/[invalid', ['regex' => true]);
    }
}
