<?php

declare(strict_types=1);

namespace MB\Filesystem\Contracts;

use MB\Filesystem\Nodes\Directory;
use MB\Filesystem\Nodes\File;
use MB\Filesystem\Nodes\Link;

/**
 * Filesystem abstraction interface.
 *
 * All methods work either with absolute paths or with paths
 * resolved relative to the base directory of a particular implementation.
 *
 * General rules:
 * - If the resource is not found, a FileNotFoundException is thrown.
 * - If an I/O error occurs, an IOException is thrown.
 * - If an operation is forbidden due to permissions, a PermissionException is thrown.
 */
interface Filesystem
{
    /**
     * Check if a file or directory exists at the given path.
     */
    public function exists(string $path): bool;

    /**
     * Read the contents of a file.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the file does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the file cannot be read.
     */
    public function get(string $path): File|Directory|Link;

    /**
     * Require a PHP file and return its result.
     *
     * @return mixed
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the file does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the file is not readable.
     */
    public function require(string $path);

    /**
     * Run a glob search by pattern.
     *
     * If the native glob fails, an empty array is returned.
     *
     * @return array<int,string>
     */
    public function glob(string $pattern, int $flags = 0): array;

    /**
     * Get the file extension without the leading dot.
     */
    public function extension(string $path): string;

    /**
     * Get the filename without extension.
     */
    public function basename(string $path): string;

    /**
     * Get the directory name of the given path.
     */
    public function dirname(string $path): string;

    /**
     * Get only the filename (with extension).
     */
    public function filename(string $path): string;

    /**
     * Check whether the given path is a file.
     */
    public function isFile(string $path): bool;

    /**
     * Check whether the given path is a directory.
     */
    public function isDirectory(string $path): bool;

    /**
     * Write contents to a file, creating the directory if necessary.
     *
     * @throws \MB\Filesystem\Exceptions\IOException On write failure.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to write.
     */
    public function put(string $path, string $contents): void;

    /**
     * Write an array as JSON into a file.
     *
     * @param array<mixed,mixed> $data
     *
     * @throws \MB\Filesystem\Exceptions\IOException On JSON encoding or write failure.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to write.
     */
    public function putJson(string $path, array $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): void;

    /**
     * Read JSON from a file and decode it.
     *
     * @param array<mixed,mixed>|object|null $default If the file does not exist and this is not null, return it.
     *
     * @return array<mixed,mixed>|object
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the file does not exist and no default was given.
     * @throws \MB\Filesystem\Exceptions\IOException If the file cannot be read or JSON cannot be decoded.
     */
    public function json(string $path, bool $assoc = true, array|object|null $default = null): array|object;

    /**
     * Read file content, or return default if file does not exist.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the file does not exist and no default was given.
     * @throws \MB\Filesystem\Exceptions\IOException If the file cannot be read.
     */
    public function content(string $path, ?string $default = null): string;

    /**
     * Read-modify-write cycle for file content. If file does not exist, updater receives empty string.
     *
     * @param File|string    $fileOrPath File node or path string.
     * @param callable(string):string $updater
     * @param bool           $atomic     When true (default), write via temp file + rename so the file is never partially written on failure. When false, write directly (faster, but a crash during write can leave the file corrupted).
     *
     * @throws \MB\Filesystem\Exceptions\IOException On read/write failure.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to write.
     */
    public function updateContent(File|string $fileOrPath, callable $updater, bool $atomic = true): void;

    /**
     * Get file metadata as a File value object.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the path is not a file.
     */
    public function file(string $path): File;

    /**
     * Get directory metadata as a Directory value object.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the path is not a directory.
     */
    public function directory(string $path): Directory;

    /**
     * Delete one or several files.
     *
     * @param string|array<int,string> $paths
     *
     * @throws \MB\Filesystem\Exceptions\IOException If one or more files could not be deleted.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to delete.
     */
    public function delete(string|array $paths): void;

    /**
     * Move or rename a file, directory, or symlink.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the source does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the move operation fails.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to write/delete.
     */
    public function move(string $from, string $to): void;

    /**
     * Rename or move a file, directory, or symlink (same as move).
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the source does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the rename operation fails.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to write/delete.
     */
    public function rename(string $from, string $to): void;

    /**
     * Copy a file.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the source file does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the copy operation fails.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to read/write.
     */
    public function copy(string $from, string $to): void;

    /**
     * Create a directory.
     *
     * @throws \MB\Filesystem\Exceptions\IOException If the directory cannot be created.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to create it.
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = true): void;

    /**
     * Set file or directory permissions (mode).
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the path does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException On failure.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to change mode.
     */
    public function chmod(string $path, int $mode): void;

    /**
     * Set access and modification time of a file or directory.
     *
     * @param int|null $mtime Unix timestamp; null for current time.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the path does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException On failure.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions.
     */
    public function touch(string $path, ?int $mtime = null): void;

    /**
     * Delete a directory. When recursive is true, deletes contents first; when false, only empty directory.
     *
     * Idempotent: if the directory does not exist, nothing happens.
     *
     * @param bool $recursive If true, delete directory and all its contents.
     *
     * @throws \MB\Filesystem\Exceptions\IOException If the path is not a directory or cannot be removed.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to delete.
     */
    public function deleteDirectory(string $directory, bool $recursive = false): void;

    /**
     * Recursively copy a directory to a new location.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the source directory does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException On copy errors.
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions.
     */
    public function copyDirectoryRecursive(string $from, string $to): void;

    /**
     * Get the list of files inside a directory.
     *
     * @return array<int,string>
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the directory does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the directory cannot be read.
     */
    public function files(string $directory, bool $recursive = false): array;

    /**
     * Get the list of subdirectories inside a directory.
     *
     * @return array<int,string>
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the directory does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the directory cannot be read.
     */
    public function directories(string $directory, bool $recursive = false): array;

    /**
     * Check whether the given path is a symbolic link.
     */
    public function isLink(string $path): bool;

    /**
     * Get symlink metadata as a Link node.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the path is not a symlink.
     */
    public function link(string $path): Link;

    /**
     * List symlinks inside a directory.
     *
     * @return array<int,Link>
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the directory does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the directory cannot be read.
     */
    public function links(string $directory, bool $recursive = false): array;

    /**
     * Create a symbolic link at linkPath pointing to target.
     *
     * Target can be relative (to the directory of linkPath) or absolute. linkPath must not exist.
     *
     * @throws \MB\Filesystem\Exceptions\IOException If the link cannot be created (e.g. link path already exists).
     * @throws \MB\Filesystem\Exceptions\PermissionException If there are no permissions to create the link.
     */
    public function createSymlink(string $target, string $linkPath): void;

    /**
     * Resolve the path to a canonical absolute path (resolve symlinks and . / ..).
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the path does not exist or resolution fails.
     */
    public function realPath(string $path): string;

    /**
     * Find file paths under a directory that contain the given pattern (literal or regex).
     *
     * @param array<string,mixed> $options Optional: "regex" (bool), "extensions" (string[]), "case_sensitive" (bool).
     *
     * @return array<int,string> Paths of files containing at least one match.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the directory does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the directory cannot be read or pattern is invalid.
     */
    public function grepPaths(string $directory, string $pattern, array $options = []): array;

    /**
     * Find files under a directory that contain the given pattern; returns File nodes.
     *
     * @param array<string,mixed> $options Optional: "regex" (bool), "extensions" (string[]), "case_sensitive" (bool).
     *
     * @return array<int,File>
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the directory does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the directory cannot be read or pattern is invalid.
     */
    public function grep(string $directory, string $pattern, array $options = []): array;

    /**
     * Find paths under a directory matching name/type/depth filters (find-like).
     *
     * @param array<string,mixed> $options Optional: "name" (string), "name_pattern" (string), "type" ('file'|'dir'|'link'), "max_depth" (int|null).
     *
     * @return array<int,string> Paths of matching files, directories, or symlinks.
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the directory does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the directory cannot be read.
     */
    public function findPaths(string $directory, array $options = []): array;

    /**
     * Find nodes under a directory matching name/type/depth filters; returns File, Directory, or Link nodes.
     *
     * @param array<string,mixed> $options Optional: "name" (string), "name_pattern" (string), "type" ('file'|'dir'|'link'), "max_depth" (int|null).
     *
     * @return array<int,File|Directory|Link>
     *
     * @throws \MB\Filesystem\Exceptions\FileNotFoundException If the directory does not exist.
     * @throws \MB\Filesystem\Exceptions\IOException If the directory cannot be read.
     */
    public function find(string $directory, array $options = []): array;
}