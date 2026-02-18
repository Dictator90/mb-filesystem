<?php

declare(strict_types=1);

use MB\Filesystem\Filesystem;

final class ContentSearcherTest extends \PHPUnit\Framework\TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mb_content_search_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->deleteDirectoryRecursive($this->tmpDir);
    }

    private function makeFilesystem(): Filesystem
    {
        return new Filesystem();
    }

    public function testSubstringPhpOnly(): void
    {
        $fs = $this->makeFilesystem();

        $dir = $this->tmpDir . DIRECTORY_SEPARATOR . 'project';
        $fs->makeDirectory($dir);

        $phpFile = $dir . DIRECTORY_SEPARATOR . 'component.php';
        $txtFile = $dir . DIRECTORY_SEPARATOR . 'component.txt';

        $needle = '$APPLICATION->IncludeComponent(';

        $fs->put($phpFile, "<?php\n{$needle} 'some:component';\n");
        $fs->put($txtFile, "This is a text file with {$needle} inside\n");

        $result = $fs->substring($dir, $needle);

        $this->assertContains($phpFile, $result);
        $this->assertNotContains($txtFile, $result, 'Non-PHP files should be ignored by default.');
    }

    public function testSubstringWithFilenameMask(): void
    {
        $fs = $this->makeFilesystem();

        $dir = $this->tmpDir . DIRECTORY_SEPARATOR . 'components';
        $fs->makeDirectory($dir);

        $needle = '$APPLICATION->IncludeComponent(';

        $componentMain = $dir . DIRECTORY_SEPARATOR . 'component.main.php';
        $componentOther = $dir . DIRECTORY_SEPARATOR . 'component.other.php';
        $unrelated = $dir . DIRECTORY_SEPARATOR . 'other.php';

        $fs->put($componentMain, "<?php\n{$needle} 'vendor:main';\n");
        $fs->put($componentOther, "<?php\n// no include here\n");
        $fs->put($unrelated, "<?php\n{$needle} 'vendor:other';\n");

        $result = $fs->substring(
            $dir,
            $needle,
            extensions: ['php'],
            filenameMask: 'component*.php',
        );

        $this->assertContains($componentMain, $result);
        $this->assertNotContains($componentOther, $result, 'File without needle should not be included.');
        $this->assertNotContains($unrelated, $result, 'File not matching filename mask should be excluded.');
    }

    public function testRegex(): void
    {
        $fs = $this->makeFilesystem();

        $dir = $this->tmpDir . DIRECTORY_SEPARATOR . 'regex';
        $fs->makeDirectory($dir);

        $file1 = $dir . DIRECTORY_SEPARATOR . 'a.php';
        $file2 = $dir . DIRECTORY_SEPARATOR . 'b.php';

        $fs->put($file1, "<?php\n\$APPLICATION->IncludeComponent ('id', 'template');\n");
        $fs->put($file2, "<?php\n// \$APPLICATION->IncludeComponent('id', 'template'); commented out\n");

        $pattern = '/\$APPLICATION->IncludeComponent\s*\(/';

        $result = $fs->regex($dir, $pattern);

        $this->assertContains($file1, $result);
        $this->assertContains($file2, $result, 'Regex currently matches even inside comments (by design).');
    }
}
