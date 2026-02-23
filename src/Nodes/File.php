<?php

declare(strict_types=1);

namespace MB\Filesystem\Nodes;

use MB\Filesystem\Contracts\Filesystem;
use MB\Filesystem\Exceptions\FileNotFoundException;
use MB\Filesystem\Exceptions\IOException;

/**
 * Rich file node: metadata plus operations that delegate to the Filesystem.
 */
final class File
{
    public function __construct(
        private readonly Filesystem $filesystem,
        public readonly string $path,
        public readonly int $size,
        public readonly int $lastModified,
        public readonly string $extension,
        public readonly string $basename,
        public readonly string $filename,
        public readonly string $dirname,
        public readonly bool $readable,
        public readonly bool $writable,
        public readonly int $mode = 0,
    ) {
    }

    /**
     * Delete this file.
     */
    public function delete(): void
    {
        $this->filesystem->delete($this->path);
    }

    /**
     * Read full file content. For very large files consider lines() to avoid loading everything into memory.
     *
     * @throws FileNotFoundException|IOException
     */
    public function content(): string
    {
        return $this->filesystem->content($this->path);
    }

    /**
     * Yield file line-by-line (memory-efficient for large files).
     *
     * @return \Generator<int, string, void, void>
     *
     * @throws FileNotFoundException|IOException
     */
    public function lines(): \Generator
    {
        $handle = @fopen($this->path, 'r');
        if ($handle === false) {
            throw new IOException("Unable to open file for reading: {$this->path}");
        }

        try {
            $index = 0;
            while (($line = fgets($handle)) !== false) {
                yield $index++ => $line;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read content of a single line by 1-based line number. Memory-efficient: reads only up to that line.
     *
     * @param int $lineNumber 1-based line number (1 = first line).
     *
     * @throws FileNotFoundException|IOException If file cannot be read or line number is out of range.
     */
    public function line(int $lineNumber): string
    {
        if ($lineNumber < 1) {
            throw new IOException("Line number must be >= 1, got {$lineNumber}");
        }

        $handle = @fopen($this->path, 'r');
        if ($handle === false) {
            throw new IOException("Unable to open file for reading: {$this->path}");
        }

        try {
            $current = 0;
            while (($line = fgets($handle)) !== false) {
                $current++;
                if ($current === $lineNumber) {
                    return $line;
                }
            }
            throw new IOException("Line {$lineNumber} does not exist in file (file has {$current} line(s)): {$this->path}");
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read-modify-write content. If file does not exist, updater receives empty string.
     *
     * @param callable(string):string $updater
     * @param bool                    $atomic When true (default), write is atomic (temp + rename). When false, write is direct (faster, but not crash-safe).
     */
    public function update(callable $updater, bool $atomic = true): void
    {
        $this->filesystem->updateContent($this, $updater, $atomic);
    }

    /**
     * Overwrite file with contents.
     */
    public function write(string $contents): void
    {
        $this->filesystem->put($this->path, $contents);
    }

    /**
     * Move this file to another path.
     */
    public function move(string $to): void
    {
        $this->filesystem->move($this->path, $to);
    }

    /**
     * Copy this file to another path.
     */
    public function copy(string $to): void
    {
        $this->filesystem->copy($this->path, $to);
    }

    /**
     * Set file permissions (mode).
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
}
