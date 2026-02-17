<?php

declare(strict_types=1);

use MB\Filesystem\ClassFinder\PhpClassFinder;
use MB\Filesystem\Filesystem;

final class PhpClassFinderTest extends \PHPUnit\Framework\TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mb_class_finder_' . uniqid();
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

    private function finder(): PhpClassFinder
    {
        return new PhpClassFinder($this->fs());
    }

    public function testFindByExtends(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/Sub');

        $fs->put('src/BaseClass.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

class BaseClass
{
}
PHP);

        $fs->put('src/ChildOne.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

class ChildOne extends BaseClass
{
}
PHP);

        $fs->put('src/Sub/ChildTwo.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Sub;

class ChildTwo extends \App\BaseClass
{
}
PHP);

        $finder = $this->finder();

        $results = $finder->findByExtends($this->tmpDir, 'App\BaseClass');

        $classes = array_column($results, 'class');

        $this->assertContains('App\ChildOne', $classes);
        $this->assertContains('App\Sub\ChildTwo', $classes);
    }

    public function testFindByImplements(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/Impl');

        $fs->put('src/MyInterface.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

interface MyInterface
{
}
PHP);

        $fs->put('src/Impl/First.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Impl;

class First implements \App\MyInterface
{
}
PHP);

        $fs->put('src/Impl/Second.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Impl;

class Second implements \App\MyInterface
{
}
PHP);

        $fs->put('src/Impl/Plain.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Impl;

class Plain
{
}
PHP);

        $finder = $this->finder();

        $results = $finder->findByImplements($this->tmpDir, 'App\MyInterface');
        $classes = array_column($results, 'class');

        $this->assertContains('App\Impl\First', $classes);
        $this->assertContains('App\Impl\Second', $classes);
        $this->assertNotContains('App\Impl\Plain', $classes);
    }
}

