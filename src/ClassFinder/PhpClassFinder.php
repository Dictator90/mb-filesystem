<?php

declare(strict_types=1);

namespace MB\Filesystem\ClassFinder;

use MB\Filesystem\Contracts\Filesystem;
use MB\Support\Collection;

/**
 * Searches PHP classes by extends/implements in a given directory tree.
 */
class PhpClassFinder
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * Find classes that extend the given base class.
     *
     * @return array<int,array<string,mixed>> List of class metadata arrays.
     */
    public function findByExtends(string $directory, string $baseClassFqcn): array
    {
        $target = $this->normalizeClassName($baseClassFqcn);
        $results = [];

        foreach ($this->phpFilesIn($directory) as $file) {
            foreach ($this->parseFile($file) as $classInfo) {
                $extends = $classInfo['extends'] ?? null;

                if ($extends !== null && $this->normalizeClassName($extends) === $target) {
                    $results[] = $classInfo;
                }
            }
        }

        return $results;
    }

    /**
     * Find classes that implement the given interface.
     *
     * @return array<int,array<string,mixed>> List of class metadata arrays.
     */
    public function findByImplements(string $directory, string $interfaceFqcn): array
    {
        $target = $this->normalizeClassName($interfaceFqcn);
        $results = [];

        foreach ($this->phpFilesIn($directory) as $file) {
            foreach ($this->parseFile($file) as $classInfo) {
                $implements = $classInfo['implements'] ?? [];

                foreach ($implements as $impl) {
                    if ($this->normalizeClassName($impl) === $target) {
                        $results[] = $classInfo;
                        break;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Get all PHP files in the given directory (recursively).
     *
     * @return array<int,string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = $this->filesystem->files($directory, true);

        return (new Collection($files))
            ->filter(static fn (string $path): bool => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php')
            ->values()
            ->all();
    }

    /**
     * Parse a PHP file and return metadata about declared classes/interfaces.
     *
     * @return array<int,array<string,mixed>>
     */
    private function parseFile(string $path): array
    {
        $code = $this->filesystem->get($path);
        $tokens = token_get_all($code);

        $namespace = '';
        $classes = [];

        $count = count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            // namespace ...
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                [$namespace, $i] = $this->parseNamespace($tokens, $i + 1, $count);
                continue;
            }

            // class / interface
            if (is_array($token) && ($token[0] === T_CLASS || $token[0] === T_INTERFACE)) {
                // пропускаем анонимные классы (class (...) )
                [$shortName, $i] = $this->parseClassName($tokens, $i + 1, $count);
                if ($shortName === null) {
                    continue;
                }

                [$extends, $implements, $i] = $this->parseInheritance($tokens, $i, $count, $namespace);

                $fqcn = $this->buildFqcn($shortName, $namespace);

                $classes[] = [
                    'class'      => $fqcn,
                    'file'       => $path,
                    'namespace'  => $namespace,
                    'short_name' => $shortName,
                    'extends'    => $extends,
                    'implements' => $implements,
                ];

                continue;
            }

            $i++;
        }

        return $classes;
    }

    /**
     * Parse a namespace declaration.
     *
     * @param array<int,mixed> $tokens
     */
    private function parseNamespace(array $tokens, int $index, int $count): array
    {
        $parts = [];

        while ($index < $count) {
            $token = $tokens[$index];

            if (is_array($token)) {
                $id = $token[0];

                if ($id === T_STRING || defined('T_NAME_QUALIFIED') && $id === T_NAME_QUALIFIED || defined('T_NAME_FULLY_QUALIFIED') && $id === T_NAME_FULLY_QUALIFIED || $id === T_NS_SEPARATOR) {
                    $parts[] = $token[1];
                    $index++;
                    continue;
                }

                if ($id === T_WHITESPACE) {
                    $index++;
                    continue;
                }
            }

            if ($token === ';' || $token === '{') {
                $index++;
                break;
            }

            $index++;
        }

        $namespace = trim(implode('', $parts), '\\');

        return [$namespace, $index];
    }

    /**
     * Parse the class/interface name after T_CLASS/T_INTERFACE.
     *
     * @param array<int,mixed> $tokens
     */
    private function parseClassName(array $tokens, int $index, int $count): array
    {
        // пропустить модификаторы и пробелы
        while ($index < $count) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_FINAL, T_ABSTRACT], true)) {
                $index++;
                continue;
            }

            break;
        }

        if ($index >= $count) {
            return [null, $index];
        }

        $token = $tokens[$index];

        // ожидаем имя класса (T_STRING); иначе это, вероятно, анонимный класс
        if (!is_array($token) || $token[0] !== T_STRING) {
            return [null, $index];
        }

        $shortName = $token[1];

        return [$shortName, $index + 1];
    }

    /**
     * Parse extends/implements part before the class body.
     *
     * @param array<int,mixed> $tokens
     *
     * @return array{0: ?string,1: array<int,string>,2: int}
     */
    private function parseInheritance(array $tokens, int $index, int $count, string $namespace): array
    {
        $extends = null;
        $implements = [];

        while ($index < $count) {
            $token = $tokens[$index];

            if ($token === '{') {
                $index++;
                break;
            }

            if (is_array($token)) {
                $id = $token[0];

                if ($id === T_EXTENDS) {
                    [$name, $index] = $this->collectQualifiedName($tokens, $index + 1, $count);
                    if ($name !== '') {
                        $extends = $this->buildFqcn($name, $namespace);
                    }
                    continue;
                }

                if ($id === T_IMPLEMENTS) {
                    $index++;

                    while ($index < $count) {
                        $t = $tokens[$index];

                        if (!is_array($t) && ($t === '{' || $t === ';')) {
                            break;
                        }

                        if (!is_array($t) && $t === ',') {
                            $index++;
                            continue;
                        }

                        if (is_array($t) && $t[0] === T_WHITESPACE) {
                            $index++;
                            continue;
                        }

                        [$name, $index] = $this->collectQualifiedName($tokens, $index, $count);
                        if ($name !== '') {
                            $implements[] = $this->buildFqcn($name, $namespace);
                        }
                    }

                    continue;
                }
            }

            $index++;
        }

        return [$extends, $implements, $index];
    }

    /**
     * Collect a qualified name (with namespace separators).
     *
     * @param array<int,mixed> $tokens
     */
    private function collectQualifiedName(array $tokens, int $index, int $count): array
    {
        $parts = [];

        while ($index < $count) {
            $token = $tokens[$index];

            if (is_array($token)) {
                $id = $token[0];

                if ($id === T_STRING || defined('T_NAME_QUALIFIED') && $id === T_NAME_QUALIFIED || defined('T_NAME_FULLY_QUALIFIED') && $id === T_NAME_FULLY_QUALIFIED || $id === T_NS_SEPARATOR) {
                    $parts[] = $token[1];
                    $index++;
                    continue;
                }

                if ($id === T_WHITESPACE) {
                    $index++;
                    continue;
                }
            }

            if (!is_array($token) && ($token === ',' || $token === '{' || $token === ';' || $token === ')')) {
                break;
            }

            break;
        }

        $name = implode('', $parts);

        return [$name, $index];
    }

    /**
     * Build a FQCN taking current namespace into account.
     */
    private function buildFqcn(string $name, string $namespace): string
    {
        $original = $name;
        $trimmed  = ltrim($name, '\\');

        if ($trimmed === '') {
            return '';
        }

        // Полностью квалифицированное имя (начинается с \) — не дополняем namespace.
        if (str_starts_with($original, '\\')) {
            return $trimmed;
        }

        if ($namespace === '') {
            return $trimmed;
        }

        return $namespace . '\\' . $trimmed;
    }

    /**
     * Normalize a class name for comparison (without leading slash, lowercased).
     */
    private function normalizeClassName(string $name): string
    {
        return strtolower(ltrim($name, '\\'));
    }
}

