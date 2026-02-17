<?php

declare(strict_types=1);

namespace MB\Filesystem\Finder;

use MB\Filesystem\Contracts\Filesystem;
use MB\Filesystem\Exceptions\FileNotFoundException;
use MB\Filesystem\Exceptions\IOException;
use MB\Filesystem\Exceptions\PermissionException;

/**
 * Utility for searching files by their contents.
 */
class ContentFinder
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * Find all files under the given directory that contain the given substring.
     *
     * @param string        $directory     Root directory to scan (recursively).
     * @param string        $needle        Substring to search for.
     * @param array<string> $extensions    File extensions (without dot) to include, defaults to ['php'].
     * @param string|null   $filenameMask  Optional filename mask (fnmatch pattern), applied to basename.
     *
     * @return array<int,string> List of file paths containing the substring.
     *
     * @throws IOException|PermissionException On directory traversal errors.
     */
    public function findBySubstring(
        string $directory,
        string $needle,
        array $extensions = ['php'],
        ?string $filenameMask = null,
    ): array {
        $candidates = $this->collectCandidateFiles($directory, $extensions, $filenameMask);
        $matches = [];

        foreach ($candidates as $path) {
            try {
                $contents = $this->filesystem->get($path);
            } catch (FileNotFoundException) {
                // File was removed between listing and reading – skip it.
                continue;
            }

            if ($needle !== '' && str_contains($contents, $needle)) {
                $matches[] = $path;
            }
        }

        return $matches;
    }

    /**
     * Find all files under the given directory that match a regular expression.
     *
     * @param string        $directory     Root directory to scan (recursively).
     * @param string        $pattern       PCRE pattern.
     * @param array<string> $extensions    File extensions (without dot) to include, defaults to ['php'].
     * @param string|null   $filenameMask  Optional filename mask (fnmatch pattern), applied to basename.
     *
     * @return array<int,string> List of file paths matching the pattern.
     *
     * @throws IOException|PermissionException On directory traversal errors.
     * @throws \InvalidArgumentException       If the provided pattern is invalid.
     */
    public function findByRegex(
        string $directory,
        string $pattern,
        array $extensions = ['php'],
        ?string $filenameMask = null,
    ): array {
        $this->assertValidRegex($pattern);

        $candidates = $this->collectCandidateFiles($directory, $extensions, $filenameMask);
        $matches = [];

        foreach ($candidates as $path) {
            try {
                $contents = $this->filesystem->get($path);
            } catch (FileNotFoundException) {
                continue;
            }

            $result = @preg_match($pattern, $contents);

            if ($result === 1) {
                $matches[] = $path;
            } elseif ($result === false) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid regex pattern during search: %s (error: %d)', $pattern, preg_last_error())
                );
            }
        }

        return $matches;
    }

    /**
     * Collect candidate files under the given directory, filtered by extension and optional filename mask.
     *
     * @param array<string> $extensions
     *
     * @return array<int,string>
     *
     * @throws IOException|PermissionException
     */
    private function collectCandidateFiles(
        string $directory,
        array $extensions,
        ?string $filenameMask,
    ): array {
        $allFiles = $this->filesystem->files($directory, true);

        $normalizedExtensions = array_values(
            array_filter(
                array_map(
                    static fn (string $ext): string => strtolower(ltrim($ext, '.')),
                    $extensions
                ),
                static fn (string $ext): bool => $ext !== ''
            )
        );

        return array_values(array_filter(
            $allFiles,
            static function (string $path) use ($normalizedExtensions, $filenameMask): bool {
                $basename = basename($path);
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if ($normalizedExtensions !== [] && !in_array($extension, $normalizedExtensions, true)) {
                    return false;
                }

                if ($filenameMask !== null && $filenameMask !== '' && !fnmatch($filenameMask, $basename)) {
                    return false;
                }

                return true;
            }
        ));
    }

    /**
     * Validate a regex pattern and throw early if invalid.
     *
     * @throws \InvalidArgumentException
     */
    private function assertValidRegex(string $pattern): void
    {
        $result = @preg_match($pattern, '');

        if ($result === false) {
            throw new \InvalidArgumentException(
                sprintf('Invalid regex pattern: %s (error: %d)', $pattern, preg_last_error())
            );
        }
    }
}

