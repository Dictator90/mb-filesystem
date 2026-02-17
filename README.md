# mb4it/filesystem

Лёгкая обёртка над локальной файловой системой PHP с удобным, предсказуемым API, основанным на исключениях.

## Требования

- PHP 8.1+

## Установка

```bash
composer require mb4it/filesystem
```

## Базовое использование

```php
<?php

use MB\Filesystem\Filesystem;
use MB\Filesystem\Exceptions\FileNotFoundException;
use MB\Filesystem\Exceptions\IOException;

$filesystem = new Filesystem(__DIR__ . '/storage');

// Запись файла
$filesystem->put('example.txt', 'Hello, world!');

// Чтение файла
try {
    $content = $filesystem->get('example.txt');
} catch (FileNotFoundException $e) {
    // файл не найден
} catch (IOException $e) {
    // другая ошибка ввода-вывода
}
```

## Работа с JSON

```php
$filesystem = new Filesystem(__DIR__ . '/storage');

// Записать массив как JSON
$filesystem->putJson('config.json', ['debug' => true]);

// Прочитать JSON как массив
$config = $filesystem->getJson('config.json', true);

// Прочитать JSON или вернуть значение по умолчанию,
// если файл отсутствует
$config = $filesystem->getJsonOrDefault('missing.json', ['debug' => false]);

// Обновление JSON через read-modify-write
$filesystem->updateJson('config.json', static function (array $data): array {
    $data['debug'] = false;
    return $data;
});
```

## Директории и рекурсивные операции

```php
$filesystem = new Filesystem(__DIR__ . '/storage');

// Создать директорию (включая родителей)
$filesystem->makeDirectory('logs/app');

// Получить файлы/папки
$files = $filesystem->files('logs', true);        // рекурсивно
$dirs  = $filesystem->directories('logs', true);  // рекурсивно

// Рекурсивное копирование и удаление
$filesystem->copyDirectoryRecursive('logs', 'logs_backup');
$filesystem->deleteDirectoryRecursive('logs_backup');
```

## Маски и фильтрация

```php
$filesystem = new Filesystem(__DIR__ . '/storage');

// Все .log-файлы (с учётом рекурсии)
$logFiles = $filesystem->filesWithExtension('logs', 'log', true);

// Файлы по маске имени
$appLogs = $filesystem->filesByPattern('logs', 'app*.log', true);
```

## Метаданные и режимы записи

```php
$filesystem = new Filesystem(__DIR__ . '/storage');

$filesystem->put('file.txt', '12345');

$size = $filesystem->size('file.txt');          // 5
$mtime = $filesystem->lastModified('file.txt'); // UNIX timestamp

// Дозапись
$filesystem->append('file.txt', '678');

// Атомарная запись
$filesystem->putAtomic('file.txt', 'new content');
```

## Path helpers and base directory

```php
$filesystem = new Filesystem(__DIR__ . '/storage');

// Paths will be resolved relative to __DIR__ . '/storage'
$filesystem->put('foo/bar.txt', 'content');

// Path helpers
$path = $filesystem->join('foo', 'bar', 'baz.txt');   // foo/bar/baz.txt
$norm = $filesystem->normalize('./foo/../bar/test');  // bar/test
$rel  = $filesystem->relative('/var/www/app', '/var/www'); // app
```

## Finding PHP classes by extends/implements

The package provides a `PhpClassFinder` utility that can find classes by base parent or implemented interface.

```php
use MB\Filesystem\Filesystem;
use MB\Filesystem\ClassFinder\PhpClassFinder;

$filesystem = new Filesystem(); // you can pass basePath if needed
$finder = new PhpClassFinder($filesystem);

// Find all classes that extend App\BaseClass
$byExtends = $finder->findByExtends(__DIR__ . '/src', App\BaseClass::class);

// Find all classes that implement App\MyInterface
$byImplements = $finder->findByImplements(__DIR__ . '/src', App\MyInterface::class);

foreach ($byImplements as $info) {
    echo $info['class'] . ' defined in ' . $info['file'] . PHP_EOL;
}
```

## Error handling

The package uses three custom exceptions:

- `MB\Filesystem\Exceptions\FileNotFoundException` — resource not found.
- `MB\Filesystem\Exceptions\IOException` — general I/O error (read/write failure, invalid JSON, etc.).
- `MB\Filesystem\Exceptions\PermissionException` — insufficient permissions to perform the operation.

It is recommended to handle them explicitly in your code:

```php
try {
    $filesystem->put('config/settings.php', '<?php return [];');
} catch (PermissionException $e) {
    // no write permissions
} catch (IOException $e) {
    // other I/O error
}
```

