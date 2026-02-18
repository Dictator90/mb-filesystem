<?php

declare(strict_types=1);

namespace MB\Filesystem\Concerns;

use MB\Filesystem\Exceptions\FileNotFoundException;

/**
 * Trait for content-based file search (substring and regex).
 *
 * Requires the using class to implement: files(string $directory, bool $recursive), get(string $path).
 */
trait ContentSearch
{
    /**
     * Find all files under the given directory that contain the given substring.
     *
     * @param array<string> $extensions   File extensions (without dot) to include, defaults to ['php'].
     * @param string|null   $filenameMask Optional filename mask (fnmatch pattern), applied to basename.
     *
     * @return array<int,string> List of file paths containing the substring.
     */
    public function substring(
        string $directory,
        string $needle,
        array $extensions = ['php'],
        ?string $filenameMask = null,
    ): array {
        $candidates = $this->collectContentSearchCandidates($directory, $extensions, $filenameMask);
        $matches = [];

        foreach ($candidates as $path) {
            try {
                $contents = $this->get($path);
            } catch (FileNotFoundException) {
                continue;
            }

            if ($needle !== '' && str_contains($contents, $needle)) {
                $matches[] = $path;
            }
        }

        return $matches;
    }

    /**
     * Find all files under the given directory whose content matches the given PCRE pattern.
     *
     * @param array<string> $extensions   File extensions (without dot) to include, defaults to ['php'].
     * @param string|null   $filenameMask Optional filename mask (fnmatch pattern), applied to basename.
     *
     * @return array<int,string> List of file paths matching the pattern.
     *
     * @throws \InvalidArgumentException If the pattern is invalid.
     */
    public function regex(
        string $directory,
        string $pattern,
        array $extensions = ['php'],
        ?string $filenameMask = null,
    ): array {
        $this->assertValidRegex($pattern);

        $candidates = $this->collectContentSearchCandidates($directory, $extensions, $filenameMask);
        $matches = [];

        foreach ($candidates as $path) {
            try {
                $contents = $this->get($path);
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
     * @param array<string> $extensions
     *
     * @return array<int,string>
     */
    private function collectContentSearchCandidates(
        string $directory,
        array $extensions,
        ?string $filenameMask,
    ): array {
        $allFiles = $this->files($directory, true);

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
