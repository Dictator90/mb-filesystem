# MB Filesystem

Lightweight wrapper around the local PHP filesystem with a convenient, predictable, exception‑based API.

## Requirements

- PHP 8.1+

## Installation

```bash
composer require mb4it/filesystem
```

## Basic usage

```php
<?php

use MB\Filesystem\Filesystem;
use MB\Filesystem\Exceptions\FileNotFoundException;
use MB\Filesystem\Exceptions\IOException;

$filesystem = new Filesystem(__DIR__ . '/storage');

// Write file
$filesystem->put('example.txt', 'Hello, world!');

// Read file
try {
    $content = $filesystem->get('example.txt');
} catch (FileNotFoundException $e) {
    // file not found
} catch (IOException $e) {
    // other I/O error
}
```

## Working with JSON

```php
$filesystem = new Filesystem(__DIR__ . '/storage');

// Write array as JSON
$filesystem->putJson('config.json', ['debug' => true]);

// Read JSON as array
$config = $filesystem->getJson('config.json', true);

// Read JSON or return default value if file is missing
$config = $filesystem->getJsonOrDefault('missing.json', ['debug' => false]);

// Update JSON via read‑modify‑write
$filesystem->updateJson('config.json', static function (array $data): array {
    $data['debug'] = false;
    return $data;
});
```

## Directories and recursive operations

```php
$filesystem = new Filesystem(__DIR__ . '/storage');

// Create directory (including parents)
$filesystem->makeDirectory('logs/app');

// Get files / directories
$files = $filesystem->files('logs', true);        // recursive
$dirs  = $filesystem->directories('logs', true);  // recursive

// Recursive copy and delete
$filesystem->copyDirectoryRecursive('logs', 'logs_backup');
$filesystem->deleteDirectoryRecursive('logs_backup');
```

## Masks and filtering

```php
$filesystem = new Filesystem(__DIR__ . '/storage');

// All .log files (with recursion)
$logFiles = $filesystem->filesWithExtension('logs', 'log', true);

// Files by name mask
$appLogs = $filesystem->filesByPattern('logs', 'app*.log', true);
```

## Metadata and write modes

```php
$filesystem = new Filesystem(__DIR__ . '/storage');

$filesystem->put('file.txt', '12345');

$size = $filesystem->size('file.txt');          // 5
$mtime = $filesystem->lastModified('file.txt'); // UNIX timestamp

// Append
$filesystem->append('file.txt', '678');

// Atomic write
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

