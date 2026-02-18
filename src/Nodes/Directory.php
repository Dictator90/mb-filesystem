<?php

declare(strict_types=1);

namespace MB\Filesystem\Nodes;

use MB\Filesystem\Contracts\Filesystem;

/**
 * Rich directory node: metadata plus operations that delegate to the Filesystem.
 */
final class Directory
{
    public function __construct(
        private readonly Filesystem $filesystem,
        public readonly string $path,
        public readonly int $lastModified,
        public readonly bool $readable,
        public readonly bool $writable,
        public readonly int $mode = 0,
    ) {
    }

    /**
     * Delete this directory. If recursive is true, deletes contents first.
     */
    public function delete(bool $recursive = false): void
    {
        $this->filesystem->deleteDirectory($this->path, $recursive);
    }

    /**
     * Set directory permissions (mode).
     */
    public function chmod(int $mode): void
    {
        $this->filesystem->chmod($this->path, $mode);
    }

    /**
     * Set access and modification time. Null for current time.
     */
    public function touch(?int $mtime = null): void
    {
        $this->filesystem->touch($this->path, $mtime);
    }

    /**
     * Ensure directory exists (create if missing). Idempotent.
     */
    public function create(int $mode = 0755, bool $recursive = true): void
    {
        $this->filesystem->makeDirectory($this->path, $mode, $recursive);
    }

    /**
     * List file paths inside this directory.
     *
     * @return array<int,string>
     */
    public function files(bool $recursive = false): array
    {
        return $this->filesystem->files($this->path, $recursive);
    }

    /**
     * List subdirectory paths inside this directory.
     *
     * @return array<int,string>
     */
    public function directories(bool $recursive = false): array
    {
        return $this->filesystem->directories($this->path, $recursive);
    }
}
