<?php

declare(strict_types=1);

use MB\Filesystem\Finder\PhpClassFinder;
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
        $filesystem->deleteDirectory($this->tmpDir, true);
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

        $results = $finder->extends($this->tmpDir, 'App\BaseClass');

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

        $results = $finder->implements($this->tmpDir, 'App\MyInterface');
        $classes = array_column($results, 'class');

        $this->assertContains('App\Impl\First', $classes);
        $this->assertContains('App\Impl\Second', $classes);
        $this->assertNotContains('App\Impl\Plain', $classes);
    }

    public function testHasTrait(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/WithTrait');

        $fs->put('src/MyTrait.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

trait MyTrait
{
}
PHP);

        $fs->put('src/WithTrait/User.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\WithTrait;

class User
{
    use \App\MyTrait;
}
PHP);

        $fs->put('src/WithTrait/Plain.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\WithTrait;

class Plain
{
}
PHP);

        $finder = $this->finder();

        $results = $finder->hasTrait($this->tmpDir, 'App\MyTrait');
        $classes = array_column($results, 'class');

        $this->assertContains('App\WithTrait\User', $classes);
        $this->assertNotContains('App\WithTrait\Plain', $classes);
    }
}

