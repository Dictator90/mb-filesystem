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

// Read file content
try {
    $content = $filesystem->content('example.txt');
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
$config = $filesystem->json('config.json', true);

// Read JSON or return default value if file is missing
$config = $filesystem->json('missing.json', true, ['debug' => false]);

// Update JSON via read‑modify‑write
$filesystem->updateJson('config.json', static function (array $data): array {
    $data['debug'] = false;
    return $data;
});
```

## Content and updateContent (plain files)

For non-JSON files you can use `content()` with an optional default and `updateContent()` for read-modify-write. `updateContent()` accepts either a path or a `File` object. For batch updates by path, prefer calling `updateContent($path, $updater)` with a string path so you avoid creating a `File` node per path (faster). The third parameter `$atomic` (default `true`) uses a temp file + rename so the file is never partially written on failure; pass `false` for higher throughput when crash-safety is not critical (e.g. logs, cache).

```php
// Read content, or default if file is missing
$text = $filesystem->content('log.txt', '');

// Read-modify-write (creates file with empty content if missing). Atomic by default.
$filesystem->updateContent('log.txt', static function (string $current): string {
    return $current . "New line\n";
});

// Non-atomic (faster) when crash during write is acceptable
$filesystem->updateContent('cache.txt', static function (string $c): string {
    return $c . "entry\n";
}, false);

// Or pass a File node when you already have one
$file = $filesystem->file('log.txt');
$filesystem->updateContent($file, static function (string $current): string {
    return $current . "Appended\n";
});
```

## get() — get a node by path

`get(string $path)` returns a **node** representing the path — it does **not** return file content. The return type is `File|Directory|Link`:

- **File** — when the path is a regular file (or a symlink is not followed; use `file()` for a file, `link()` for a symlink).
- **Directory** — when the path is a directory.
- **Link** — when the path is a symbolic link (symlink).

Use `content($path)` or `$file->content()` to read file content as a string. Example:

```php
$node = $filesystem->get('path/to/something');

if ($node instanceof \MB\Filesystem\Nodes\File) {
    $text = $node->content();
} elseif ($node instanceof \MB\Filesystem\Nodes\Directory) {
    $list = $node->files(true);
} elseif ($node instanceof \MB\Filesystem\Nodes\Link) {
    $resolved = $node->resolve();  // File|Directory|null
}
```

## File, Directory, and Link (nodes)

`file()`, `directory()`, and `link()` return rich nodes with metadata and operations. Use them to read, update, or delete without passing the path again. You can also obtain a node with `get($path)` (see above).

### Node types and main methods

| Node       | Main methods / metadata |
|-----------|--------------------------|
| **File**  | `path`, `size`, `lastModified`, `extension`, `basename`, `filename`, `dirname`, `readable`, `writable`, `mode` — `content()`, `lines()`, `line($n)`, `update()`, `write()`, `delete()`, `move()`, `copy()`, `chmod()`, `touch()` |
| **Directory** | `path`, `lastModified`, `readable`, `writable`, `mode` — `files()`, `directories()`, `create()`, `delete($recursive)`, `chmod()`, `touch()` |
| **Link**  | `path`, `target`, `isBroken`, `targetType` — `target()`, `isBroken()`, `resolve(): File|Directory|null`, `delete()` |

### File

For large files use `lines()` instead of `content()` to avoid loading the whole file; use `line($lineNumber)` to read a single line by 1-based index.

```php
$file = $filesystem->file('path/to/file.txt');

$file->content();           // full content (string)
$file->line(42);            // 42nd line (1-based)
foreach ($file->lines() as $i => $line) { /* line-by-line, memory-efficient */ }
$file->update(fn (string $c) => $c . "\nnew line");  // optional second arg: $atomic = true
$file->write('new content');
$file->delete();
$file->move('other.txt');
$file->copy('backup.txt');
$file->chmod(0644);
$file->touch();             // or touch($mtime)
```

### Directory

```php
$dir = $filesystem->directory('path/to/dir');

$dir->files(true);          // list files, recursive
$dir->directories(true);   // list subdirs
$dir->create(0755, true);  // ensure exists
$dir->delete(true);        // delete recursively
$dir->chmod(0755);
$dir->touch($mtime);
```

### Link (symlinks)

Create a symlink with `createSymlink($target, $linkPath)`. Target can be relative (to the link’s directory) or absolute; link path must not exist. Use `isLink($path)` to check, `link($path)` to get a `Link` node. `resolve()` returns the target as `File` or `Directory`, or `null` if the link is broken.

```php
$filesystem->put('target.txt', 'content');
$filesystem->createSymlink('target.txt', 'link.txt');  // link path must not exist

if ($filesystem->isLink('my-link')) {
    $link = $filesystem->link('my-link');
    $link->target;       // target path
    $link->isBroken();   // true if target does not exist
    $resolved = $link->resolve();  // File|Directory|null
}
$links = $filesystem->links('dir', true);  // list symlinks, optional recursive
```

## Move, rename, and real path

`move($from, $to)` and `rename($from, $to)` do the same thing: move or rename a file, directory, or symlink. Both use PHP’s `rename()`; for directories, source and destination must be on the same filesystem.

`realPath($path)` returns the canonical absolute path (symlinks and `..`/`.` resolved). Throws if the path does not exist.

```php
$filesystem->move('old.txt', 'new.txt');
$filesystem->rename('olddir', 'newdir');   // rename directory
$canonical = $filesystem->realPath('config/../logs/app.log');
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
$filesystem->deleteDirectory('logs_backup', true);
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

// Permissions and mtime
$filesystem->chmod('file.txt', 0644);
$filesystem->touch('file.txt', $mtime);         // or null for current time

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

## Finding PHP classes by extends/implements/traits

The package provides a `ClassFinder` utility that can find classes by base parent, implemented interface, or used trait using static token-based parsing (no autoload, no ReflectionClass).

```php
use MB\Filesystem\Filesystem;
use MB\Filesystem\Finder\ClassFinder;

$filesystem = new Filesystem(); // you can pass basePath if needed
$finder = new ClassFinder($filesystem);

// Find all classes that extend App\BaseClass
$byExtends = $finder->extends(__DIR__ . '/src', App\BaseClass::class);

// Find all classes that implement App\MyInterface
$byImplements = $finder->implements(__DIR__ . '/src', App\MyInterface::class);

// Find all classes that use App\MyTrait
$withTrait = $finder->hasTrait(__DIR__ . '/src', App\MyTrait::class);

foreach ($byImplements as $info) {
    echo $info['class'] . ' defined in ' . $info['file'] . PHP_EOL;
}
```

## Content search (substring and regex)

Search for files by content using `substring()` and `regex()` on the Filesystem instance.

```php
$filesystem = new Filesystem();

// Find all PHP files under /bitrix that contain $APPLICATION->IncludeComponent(
$files = $filesystem->substring(
    __DIR__ . '/bitrix',
    '$APPLICATION->IncludeComponent(',
    extensions: ['php'],
);

// Find only component*.php files that contain the substring
$componentFiles = $filesystem->substring(
    __DIR__ . '/bitrix',
    '$APPLICATION->IncludeComponent(',
    extensions: ['php'],
    filenameMask: 'component*.php',
);

// Use regex to find calls with arbitrary whitespace
$regexMatches = $filesystem->regex(
    __DIR__ . '/bitrix',
    '/\$APPLICATION->IncludeComponent\s*\(/',
    extensions: ['php'],
);
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

