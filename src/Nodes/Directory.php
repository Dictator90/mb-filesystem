<?php

declare(strict_types=1);

namespace MB\Filesystem\Nodes;

/**
 * Value object representing directory metadata.
 */
final class Directory
{
    public function __construct(
        public readonly string $path,
        public readonly int $lastModified,
        public readonly bool $readable,
        public readonly bool $writable,
    ) {
    }
}
