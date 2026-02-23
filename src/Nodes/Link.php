<?php

declare(strict_types=1);

namespace MB\Filesystem\Nodes;

use MB\Filesystem\Contracts\Filesystem;
use MB\Filesystem\Exceptions\FileNotFoundException;

/**
 * Symlink node: represents a symbolic link, its target, and resolution helpers.
 */
final class Link
{
    public function __construct(
        private readonly Filesystem $filesystem,
        /**
         * Absolute path to the symlink itself.
         */
        public readonly string $path,
        /**
         * Target path as resolved from the link (may be non-existent).
         */
        public readonly string $target,
        /**
         * Whether the target currently does not exist on disk.
         */
        public readonly bool $isBroken,
        /**
         * Optional target type hint: "file", "directory" or null when unknown.
         */
        public readonly ?string $targetType = null,
    ) {
    }

    /**
     * Delete this symlink (does not touch the target).
     */
    public function delete(): void
    {
        $this->filesystem->delete($this->path);
    }

    /**
     * Check if this link is broken (target does not exist).
     */
    public function isBroken(): bool
    {
        return $this->isBroken;
    }

    /**
     * Try to resolve this link to a File or Directory node.
     *
     * Returns null when the link is broken or the target is neither file nor directory.
     */
    public function resolve(): File|Directory|null
    {
        if ($this->isBroken) {
            return null;
        }

        try {
            if ($this->targetType === 'file') {
                return $this->filesystem->file($this->target);
            }

            if ($this->targetType === 'directory') {
                return $this->filesystem->directory($this->target);
            }

            // Fallback: try file then directory.
            try {
                return $this->filesystem->file($this->target);
            } catch (FileNotFoundException) {
                return $this->filesystem->directory($this->target);
            }
        } catch (FileNotFoundException) {
            // Target disappeared between discovery and resolution.
            return null;
        }
    }
}

