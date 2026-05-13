<?php

declare(strict_types=1);

use MB\Filesystem\Finder\ClassFinder;
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

    private function finder(): ClassFinder
    {
        return new ClassFinder($this->fs());
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

use \App\BaseClass;

class ChildTwo extends BaseClass
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

use App\MyInterface;

class First implements MyInterface
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

    public function testUseImportsForExtendsAndImplementsWithAlias(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/UseImports');

        $fs->put('src/UseImports/Classes.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\UseImports;

use My\ParentNamespace\ParentClass;
use My\Interfaces\ImportedInterface;
use My\ParentNamespace\AltParent as BaseParent;
use My\Interfaces\AltInterface as AliasInterface;

class ChildWithImportedExtends extends ParentClass
{
}

class ChildWithAliasExtends extends BaseParent
{
}

class ChildWithImportedImplements implements ImportedInterface
{
}

class ChildWithAliasImplements implements AliasInterface
{
}
PHP);

        $finder = $this->finder();

        $extendedImported = $finder->extends($this->tmpDir, 'My\ParentNamespace\ParentClass');
        $extendedAlias    = $finder->extends($this->tmpDir, 'My\ParentNamespace\AltParent');
        $implementedImported = $finder->implements($this->tmpDir, 'My\Interfaces\ImportedInterface');
        $implementedAlias    = $finder->implements($this->tmpDir, 'My\Interfaces\AltInterface');

        $extendedImportedClasses    = array_column($extendedImported, 'class');
        $extendedAliasClasses       = array_column($extendedAlias, 'class');
        $implementedImportedClasses = array_column($implementedImported, 'class');
        $implementedAliasClasses    = array_column($implementedAlias, 'class');

        $this->assertContains('App\UseImports\ChildWithImportedExtends', $extendedImportedClasses);
        $this->assertContains('App\UseImports\ChildWithAliasExtends', $extendedAliasClasses);
        $this->assertContains('App\UseImports\ChildWithImportedImplements', $implementedImportedClasses);
        $this->assertContains('App\UseImports\ChildWithAliasImplements', $implementedAliasClasses);
    }

    public function testFileLevelUseImportsForTraitsWithAlias(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/WithImportedTraits');

        $fs->put('src/WithImportedTraits/Users.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\WithImportedTraits;

use My\Traits\Loggable;
use My\Traits\Loggable as LogAlias;

class UserWithImportedTrait
{
    use Loggable;
}

class UserWithAliasTrait
{
    use LogAlias;
}
PHP);

        $finder = $this->finder();

        $results = $finder->hasTrait($this->tmpDir, 'My\Traits\Loggable');
        $classes = array_column($results, 'class');

        $this->assertContains('App\WithImportedTraits\UserWithImportedTrait', $classes);
        $this->assertContains('App\WithImportedTraits\UserWithAliasTrait', $classes);
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

    public function testFindsDirectChild(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/DeepDirect');

        $fs->put('src/DeepDirect/BaseClass.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\DeepDirect;

class BaseClass
{
}
PHP);

        $fs->put('src/DeepDirect/DirectChild.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\DeepDirect;

class DirectChild extends BaseClass
{
}
PHP);

        $results = $this->finder()->extends($this->tmpDir, 'App\DeepDirect\BaseClass');
        $classes = array_column($results, 'class');

        $this->assertContains('App\DeepDirect\DirectChild', $classes);
    }

    public function testFindsNestedChildByDefault(): void
    {
        $this->writeInheritanceChain('App\DeepDefault', 'src/DeepDefault');

        $results = $this->finder()->extends($this->tmpDir, 'App\DeepDefault\BaseClass');
        $classes = array_column($results, 'class');

        $this->assertContains('App\DeepDefault\MiddleClass', $classes);
        $this->assertContains('App\DeepDefault\FinalClass', $classes);
    }

    public function testCanSearchOnlyDirectChildren(): void
    {
        $this->writeInheritanceChain('App\DirectOnly', 'src/DirectOnly');

        $results = $this->finder()->extends($this->tmpDir, 'App\DirectOnly\BaseClass', false);
        $classes = array_column($results, 'class');

        $this->assertContains('App\DirectOnly\MiddleClass', $classes);
        $this->assertNotContains('App\DirectOnly\FinalClass', $classes);
    }

    public function testDoesNotReturnUnrelatedClasses(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/Unrelated');

        $fs->put('src/Unrelated/Classes.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Unrelated;

class BaseClass
{
}

class ChildClass extends BaseClass
{
}

class OtherBase
{
}

class OtherChild extends OtherBase
{
}

class PlainClass
{
}
PHP);

        $results = $this->finder()->extends($this->tmpDir, 'App\Unrelated\BaseClass');
        $classes = array_column($results, 'class');

        $this->assertContains('App\Unrelated\ChildClass', $classes);
        $this->assertNotContains('App\Unrelated\OtherBase', $classes);
        $this->assertNotContains('App\Unrelated\OtherChild', $classes);
        $this->assertNotContains('App\Unrelated\PlainClass', $classes);
    }

    public function testResolvesUseAliasesInDeepSearch(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/AliasDeep/Repositories');
        $fs->makeDirectory('src/AliasDeep/Services');

        $fs->put('src/AliasDeep/BaseClass.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\AliasDeep;

class BaseClass
{
}
PHP);

        $fs->put('src/AliasDeep/Repositories/Repository.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\AliasDeep\Repositories;

use App\AliasDeep\BaseClass as ParentClass;

class Repository extends ParentClass
{
}
PHP);

        $fs->put('src/AliasDeep/Services/ProductRepository.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\AliasDeep\Services;

use App\AliasDeep\Repositories\Repository;

class ProductRepository extends Repository
{
}
PHP);

        $results = $this->finder()->extends($this->tmpDir, 'App\AliasDeep\BaseClass');
        $classes = array_column($results, 'class');

        $this->assertContains('App\AliasDeep\Repositories\Repository', $classes);
        $this->assertContains('App\AliasDeep\Services\ProductRepository', $classes);
    }

    public function testResolvesFullyQualifiedExtends(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/FullyQualified/Services');

        $fs->put('src/FullyQualified/BaseClass.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\FullyQualified;

class BaseClass
{
}
PHP);

        $fs->put('src/FullyQualified/Services/Service.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\FullyQualified\Services;

class Service extends \App\FullyQualified\BaseClass
{
}
PHP);

        $results = $this->finder()->extends($this->tmpDir, 'App\FullyQualified\BaseClass');
        $classes = array_column($results, 'class');

        $this->assertContains('App\FullyQualified\Services\Service', $classes);
    }

    public function testStopsWhenParentClassIsOutsideScannedDirectory(): void
    {
        $fs = $this->fs();

        $fs->makeDirectory('src/ExternalParent');

        $fs->put('src/ExternalParent/ExternalChild.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\ExternalParent;

class ExternalChild extends \Vendor\BaseClass
{
}
PHP);

        $results = $this->finder()->extends($this->tmpDir, 'App\ExternalParent\BaseClass');
        $classes = array_column($results, 'class');

        $this->assertSame([], $classes);
    }

    private function writeInheritanceChain(string $namespace, string $directory): void
    {
        $fs = $this->fs();

        $fs->makeDirectory($directory);

        $fs->put($directory . '/Classes.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

class BaseClass
{
}

class MiddleClass extends BaseClass
{
}

class FinalClass extends MiddleClass
{
}
PHP);
    }

}

