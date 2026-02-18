<?php

declare(strict_types=1);

namespace MB\Filesystem\Nodes;

/**
 * Value object representing file metadata.
 */
final class File
{
    public function __construct(
        public readonly string $path,
        public readonly int $size,
        public readonly int $lastModified,
        public readonly string $extension,
        public readonly string $basename,
        public readonly string $filename,
        public readonly string $dirname,
        public readonly bool $readable,
        public readonly bool $writable,
    ) {
    }
}
