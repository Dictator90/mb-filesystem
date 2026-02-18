<?php

declare(strict_types=1);

namespace MB\Filesystem;

use MB\Filesystem\Concerns\ContentSearch;
use MB\Filesystem\Contracts\Filesystem as FilesystemContract;
use MB\Filesystem\Exceptions\FileNotFoundException;
use MB\Filesystem\Exceptions\IOException;
use MB\Filesystem\Exceptions\PermissionException;
use MB\Filesystem\Nodes\Directory;
use MB\Filesystem\Nodes\File;
use MB\Support\Collection;

/**
 * Filesystem implementation on top of native PHP filesystem functions.
 */
class Filesystem implements FilesystemContract
{
    use ContentSearch;
    /**
     * Base directory that relative paths are resolved against.
     * If null, paths are used as-is.
     */
    private ?string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath !== null ? rtrim($basePath, DIRECTORY_SEPARATOR) : null;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->resolvePath($path));
    }

    public function get(string $path): string
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException($fullPath);
        }

        if (!is_readable($fullPath)) {
            throw new PermissionException($fullPath, 'read');
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            throw new IOException("Unable to read file: {$fullPath}");
        }

        return $content;
    }

    public function require(string $path)
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException($fullPath);
        }

        if (!is_readable($fullPath)) {
            throw new PermissionException($fullPath, 'read');
        }

        /** @noinspection PhpIncludeInspection */
        return require $fullPath;
    }

    public function glob(string $pattern, int $flags = 0): array
    {
        $result = @glob($pattern, $flags);
        return $result !== false ? $result : [];
    }

    public function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public function basename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public function dirname(string $path): string
    {
        return \dirname($path);
    }

    public function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * Размер файла в байтах.
     */
    public function size(string $path): int
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException($fullPath);
        }

        if (!is_readable($fullPath)) {
            throw new PermissionException($fullPath, 'stat');
        }

        $size = @filesize($fullPath);
        if ($size === false) {
            throw new IOException("Unable to get size of file: {$fullPath}");
        }

        return $size;
    }

    /**
     * Время последней модификации файла (UNIX timestamp).
     */
    public function lastModified(string $path): int
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException($fullPath);
        }

        if (!is_readable($fullPath)) {
            throw new PermissionException($fullPath, 'stat');
        }

        $time = @filemtime($fullPath);
        if ($time === false) {
            throw new IOException("Unable to get last modified time of file: {$fullPath}");
        }

        return $time;
    }

    /**
     * Сахарные методы для проверки существования файла/директории.
     */
    public function existsFile(string $path): bool
    {
        return $this->isFile($path);
    }

    public function existsDirectory(string $path): bool
    {
        return $this->isDirectory($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($this->resolvePath($path));
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($this->resolvePath($path));
    }

    public function put(string $path, string $contents): void
    {
        $fullPath = $this->resolvePath($path);
        $directory = \dirname($fullPath);

        $this->ensureDirectoryExists($directory);

        if (!is_writable($directory)) {
            throw new PermissionException($directory, 'write');
        }

        $bytes = @file_put_contents($fullPath, $contents);
        if ($bytes === false) {
            throw new IOException("Unable to write file: {$fullPath}");
        }
    }

    /**
     * Дозаписывает данные в конец файла, создавая его при необходимости.
     */
    public function append(string $path, string $contents): void
    {
        $fullPath = $this->resolvePath($path);
        $directory = \dirname($fullPath);

        $this->ensureDirectoryExists($directory);

        if (!is_writable($directory) && (!file_exists($fullPath) || !is_writable($fullPath))) {
            throw new PermissionException($fullPath, 'append');
        }

        $bytes = @file_put_contents($fullPath, $contents, FILE_APPEND | LOCK_EX);
        if ($bytes === false) {
            throw new IOException("Unable to append to file: {$fullPath}");
        }
    }

    /**
     * Атомарная запись файла через временный файл и rename.
     */
    public function putAtomic(string $path, string $contents): void
    {
        $fullPath = $this->resolvePath($path);
        $directory = \dirname($fullPath);

        $this->ensureDirectoryExists($directory);

        if (!is_writable($directory)) {
            throw new PermissionException($directory, 'write');
        }

        $tempPath = $directory . DIRECTORY_SEPARATOR . basename($fullPath) . '.' . uniqid('tmp', true);

        $bytes = @file_put_contents($tempPath, $contents, LOCK_EX);
        if ($bytes === false) {
            @unlink($tempPath);
            throw new IOException("Unable to write temporary file: {$tempPath}");
        }

        if (!@rename($tempPath, $fullPath)) {
            @unlink($tempPath);
            throw new IOException("Unable to move temporary file to destination: {$fullPath}");
        }
    }

    public function putJson(string $path, array $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): void
    {
        $json = $this->encodeJson($data, $flags);
        $this->put($path, $json);
    }

    public function json(string $path, bool $assoc = true, array|object|null $default = null): array|object
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            if ($default !== null) {
                return $default;
            }
            throw new FileNotFoundException($fullPath);
        }

        $contents = $this->get($path);

        $decoded = json_decode($contents, $assoc);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new IOException(
                sprintf(
                    'Unable to decode JSON from file %s: %s',
                    $fullPath,
                    json_last_error_msg()
                )
            );
        }

        if ($assoc) {
            if (!is_array($decoded)) {
                throw new IOException("Decoded JSON is not an array for file: {$fullPath}");
            }

            /** @var array<mixed,mixed> $decoded */
            return $decoded;
        }

        if (!is_object($decoded)) {
            throw new IOException("Decoded JSON is not an object for file: {$fullPath}");
        }

        /** @var object $decoded */
        return $decoded;
    }

    public function content(string $path, ?string $default = null): string
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            if ($default !== null) {
                return $default;
            }
            throw new FileNotFoundException($fullPath);
        }

        return $this->get($path);
    }

    public function updateContent(string $path, callable $updater): void
    {
        $current = '';
        try {
            $current = $this->get($path);
        } catch (FileNotFoundException) {
            // pass empty string to updater
        }

        $newContent = $updater($current);
        if (!is_string($newContent)) {
            throw new IOException('Content updater must return a string.');
        }

        $this->putAtomic($path, $newContent);
    }

    public function file(string $path): File
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException($fullPath);
        }

        $size = @filesize($fullPath);
        if ($size === false) {
            throw new IOException("Unable to get size of file: {$fullPath}");
        }

        $mtime = @filemtime($fullPath);
        if ($mtime === false) {
            throw new IOException("Unable to get last modified time of file: {$fullPath}");
        }

        return new File(
            path: $fullPath,
            size: $size,
            lastModified: $mtime,
            extension: pathinfo($fullPath, PATHINFO_EXTENSION),
            basename: pathinfo($fullPath, PATHINFO_FILENAME),
            filename: basename($fullPath),
            dirname: \dirname($fullPath),
            readable: is_readable($fullPath),
            writable: is_writable($fullPath),
        );
    }

    public function directory(string $path): Directory
    {
        $fullPath = $this->resolvePath($path);

        if (!is_dir($fullPath)) {
            throw new FileNotFoundException($fullPath);
        }

        $mtime = @filemtime($fullPath);
        if ($mtime === false) {
            $mtime = 0;
        }

        return new Directory(
            path: $fullPath,
            lastModified: $mtime,
            readable: is_readable($fullPath),
            writable: is_writable($fullPath),
        );
    }

    /**
     * Read-modify-write cycle for a JSON file. Missing file is treated as empty array.
     *
     * @param callable(array<mixed,mixed>):array<mixed,mixed> $updater
     */
    public function updateJson(string $path, callable $updater): void
    {
        $data = $this->json($path, true, []);

        if (!is_array($data)) {
            throw new IOException('JSON file did not decode to an array.');
        }

        $newData = $updater($data);

        if (!is_array($newData)) {
            throw new IOException('JSON updater must return an array.');
        }

        $json = $this->encodeJson($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->putAtomic($path, $json);
    }

    public function delete(string|array $paths): void
    {
        $paths = is_array($paths) ? $paths : func_get_args();
        $errors = [];

        foreach ($paths as $path) {
            $fullPath = $this->resolvePath($path);

            if (!file_exists($fullPath)) {
                continue;
            }

            if (!is_writable($fullPath)) {
                $errors[] = $fullPath;
                continue;
            }

            if (!@unlink($fullPath)) {
                $errors[] = $fullPath;
            }
        }

        if (!empty($errors)) {
            if ($this->hasPermissionIssue($errors)) {
                throw new PermissionException(implode(', ', $errors), 'delete');
            }

            throw new IOException('Unable to delete files: ' . implode(', ', $errors));
        }
    }

    public function move(string $from, string $to): void
    {
        $fromPath = $this->resolvePath($from);
        $toPath = $this->resolvePath($to);

        if (!is_file($fromPath)) {
            throw new FileNotFoundException($fromPath);
        }

        $this->ensureDirectoryExists(\dirname($toPath));

        if (!is_writable(\dirname($toPath))) {
            throw new PermissionException(\dirname($toPath), 'write');
        }

        if (!@rename($fromPath, $toPath)) {
            throw new IOException("Unable to move file from {$fromPath} to {$toPath}");
        }
    }

    public function copy(string $from, string $to): void
    {
        $fromPath = $this->resolvePath($from);
        $toPath = $this->resolvePath($to);

        if (!is_file($fromPath)) {
            throw new FileNotFoundException($fromPath);
        }

        $this->ensureDirectoryExists(\dirname($toPath));

        if (!is_readable($fromPath)) {
            throw new PermissionException($fromPath, 'read');
        }

        if (!is_writable(\dirname($toPath))) {
            throw new PermissionException(\dirname($toPath), 'write');
        }

        if (!@copy($fromPath, $toPath)) {
            throw new IOException("Unable to copy file from {$fromPath} to {$toPath}");
        }
    }

    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = true): void
    {
        $fullPath = $this->resolvePath($path);

        if (is_dir($fullPath)) {
            return;
        }

        $parent = \dirname($fullPath);
        if (!is_dir($parent)) {
            if (!$recursive) {
                throw new IOException("Parent directory does not exist: {$parent}");
            }

            $this->makeDirectory($parent, $mode, true);
        }

        if (!@mkdir($fullPath, $mode, false) && !is_dir($fullPath)) {
            if (!is_writable($parent)) {
                throw new PermissionException($parent, 'create directory');
            }

            throw new IOException("Unable to create directory: {$fullPath}");
        }
    }

    public function deleteDirectory(string $directory): void
    {
        $fullPath = $this->resolvePath($directory);

        if (!file_exists($fullPath)) {
            return;
        }

        if (!is_dir($fullPath)) {
            throw new IOException("Not a directory: {$fullPath}");
        }

        if (!is_writable($fullPath)) {
            throw new PermissionException($fullPath, 'delete directory');
        }

        if (!@rmdir($fullPath)) {
            throw new IOException("Unable to delete directory: {$fullPath}");
        }
    }

    public function deleteDirectoryRecursive(string $directory): void
    {
        $fullPath = $this->resolvePath($directory);

        if (!file_exists($fullPath)) {
            return;
        }

        if (!is_dir($fullPath)) {
            throw new IOException("Not a directory: {$fullPath}");
        }

        $items = @scandir($fullPath);
        if ($items === false) {
            throw new IOException("Unable to scan directory: {$fullPath}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $fullPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                $this->delete($path);
            }
        }

        $this->deleteDirectory($fullPath);
    }

    public function copyDirectoryRecursive(string $from, string $to): void
    {
        $fromPath = $this->resolvePath($from);
        $toPath = $this->resolvePath($to);

        if (!is_dir($fromPath)) {
            throw new FileNotFoundException($fromPath);
        }

        $this->ensureDirectoryExists($toPath);

        $items = @scandir($fromPath);
        if ($items === false) {
            throw new IOException("Unable to scan directory: {$fromPath}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $source = $fromPath . DIRECTORY_SEPARATOR . $item;
            $destination = $toPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($source)) {
                $this->copyDirectoryRecursive($source, $destination);
            } else {
                $this->copy($source, $destination);
            }
        }
    }

    public function files(string $directory, bool $recursive = false): array
    {
        $fullPath = $this->resolvePath($directory);

        if (!is_dir($fullPath)) {
            throw new FileNotFoundException($fullPath);
        }

        $result = [];
        $items = @scandir($fullPath);

        if ($items === false) {
            throw new IOException("Unable to scan directory: {$fullPath}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $fullPath . DIRECTORY_SEPARATOR . $item;

            if (is_file($path)) {
                $result[] = $path;
            } elseif ($recursive && is_dir($path)) {
                $result = array_merge($result, $this->files($path, true));
            }
        }

        return $result;
    }

    /**
     * Возвращает файлы с заданным расширением.
     *
     * @return array<int,string>
     */
    public function filesWithExtension(string $directory, string $extension, bool $recursive = false): array
    {
        $extension = ltrim($extension, '.');
        $files = $this->files($directory, $recursive);

        return Collection::make($files)
            ->filter(static function (string $path) use ($extension): bool {
                return strcasecmp(pathinfo($path, PATHINFO_EXTENSION), $extension) === 0;
            })
            ->values()
            ->all();
    }

    /**
     * Возвращает файлы, имя которых соответствует простому шаблону (fnmatch).
     *
     * Шаблон применяется к basename файла (без пути).
     *
     * @return array<int,string>
     */
    public function filesByPattern(string $directory, string $pattern, bool $recursive = false): array
    {
        $files = $this->files($directory, $recursive);

        return Collection::make($files)
            ->filter(static function (string $path) use ($pattern): bool {
                return fnmatch($pattern, basename($path));
            })
            ->values()
            ->all();
    }

    public function directories(string $directory, bool $recursive = false): array
    {
        $fullPath = $this->resolvePath($directory);

        if (!is_dir($fullPath)) {
            throw new FileNotFoundException($fullPath);
        }

        $result = [];
        $items = @scandir($fullPath);

        if ($items === false) {
            throw new IOException("Unable to scan directory: {$fullPath}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $fullPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $result[] = $path;

                if ($recursive) {
                    $result = array_merge($result, $this->directories($path, true));
                }
            }
        }

        return $result;
    }

    /**
     * Собирает путь из сегментов и нормализует его.
     */
    public function join(string ...$segments): string
    {
        $segments = Collection::make($segments)
            ->map(fn (string $segment) => trim($segment, DIRECTORY_SEPARATOR . '/'))
            ->values()
            ->all();


        return $this->normalizePath(implode(DIRECTORY_SEPARATOR, $segments));
    }

    /**
     * Нормализует путь: приводит разделители к системным, упрощает . и ..
     */
    public function normalize(string $path): string
    {
        return $this->normalizePath($path);
    }

    /**
     * Возвращает путь к $path относительно $from.
     */
    public function relative(string $path, string $from): string
    {
        $target = $this->normalizePath($this->resolvePath($path));
        $fromPath = $this->normalizePath($this->resolvePath($from));

        $targetParts = explode(DIRECTORY_SEPARATOR, trim($target, DIRECTORY_SEPARATOR));
        $fromParts = explode(DIRECTORY_SEPARATOR, trim($fromPath, DIRECTORY_SEPARATOR));

        // Найти общую часть
        $length = min(count($targetParts), count($fromParts));
        $commonLength = 0;

        for ($i = 0; $i < $length; $i++) {
            if ($targetParts[$i] !== $fromParts[$i]) {
                break;
            }
            $commonLength++;
        }

        $up = array_fill(0, count($fromParts) - $commonLength, '..');
        $down = array_slice($targetParts, $commonLength);

        $relativeParts = array_merge($up, $down);

        return $relativeParts === [] ? '.' : implode(DIRECTORY_SEPARATOR, $relativeParts);
    }

    protected function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        $this->makeDirectory($directory, 0755, true);
    }

    /**
     * Внутренняя нормализация путей: заменяет разделители и упрощает сегменты.
     */
    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Привести все разделители к / для простой обработки
        $path = str_replace(['\\', '/'], '/', $path);

        $isAbsolute = str_starts_with($path, '/')
            || preg_match('#^[A-Za-z]:/#', $path) === 1
            || str_starts_with($path, '//');

        $segments = explode('/', $path);
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);
                continue;
            }

            $normalized[] = $segment;
        }

        $normalizedPath = implode(DIRECTORY_SEPARATOR, $normalized);

        if ($isAbsolute) {
            // Сохранить ведущий слеш или диск
            if (preg_match('#^[A-Za-z]:/#', $path) === 1) {
                $drive = substr($path, 0, 2);
                $normalizedPath = $drive . DIRECTORY_SEPARATOR . ltrim($normalizedPath, DIRECTORY_SEPARATOR);
            } else {
                $normalizedPath = DIRECTORY_SEPARATOR . ltrim($normalizedPath, DIRECTORY_SEPARATOR);
            }
        }

        return $normalizedPath;
    }

    /**
     * Кодирует массив в JSON с обработкой ошибок.
     *
     * @param array<mixed,mixed> $data
     */
    private function encodeJson(array $data, int $flags): string
    {
        $json = json_encode($data, $flags);
        if ($json === false) {
            throw new IOException('Unable to encode data to JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Разрешает путь относительно базового каталога, если он задан.
     */
    private function resolvePath(string $path): string
    {
        if ($this->basePath === null || $this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        // Windows (C:\ или \\server\share)
        if (preg_match('#^[A-Za-z]:\\\\#', $path) === 1 || str_starts_with($path, '\\\\')) {
            return true;
        }

        // Unix-пути
        return str_starts_with($path, '/');
    }

    /**
     * Грубая эвристика: если хотя бы один путь недоступен для записи — считаем, что есть проблема с правами.
     *
     * @param array<int,string> $paths
     */
    private function hasPermissionIssue(array $paths): bool
    {
        foreach ($paths as $path) {
            if (!is_writable($path)) {
                return true;
            }
        }

        return false;
    }
}