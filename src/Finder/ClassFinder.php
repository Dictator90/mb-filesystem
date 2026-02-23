<?php

declare(strict_types=1);

namespace MB\Filesystem\Finder;

use MB\Filesystem\Contracts\Filesystem;
use MB\Support\Collection;

/**
 * Searches PHP classes by extends/implements/traits in a given directory tree using static token parsing.
 *
 * This avoids loading classes (no ReflectionClass/autoload), which makes bulk scans over many files
 * faster and free from side effects.
 */
class ClassFinder
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
    public function extends(string $directory, string $baseClassFqcn): array
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
    public function implements(string $directory, string $interfaceFqcn): array
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
     * Find classes that use the given trait.
     *
     * @return array<int,array<string,mixed>> List of class metadata arrays.
     */
    public function hasTrait(string $directory, string $traitFqcn): array
    {
        $target = $this->normalizeClassName($traitFqcn);
        $results = [];

        foreach ($this->phpFilesIn($directory) as $file) {
            foreach ($this->parseFile($file) as $classInfo) {
                $traits = $classInfo['traits'] ?? [];

                foreach ($traits as $trait) {
                    if ($this->normalizeClassName($trait) === $target) {
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
        $code = $this->filesystem->content($path);
        $tokens = token_get_all($code);

        $namespace = '';
        $classes = [];

        $count = count($tokens);
        $useMap = $this->parseFileLevelUseStatements($tokens, $count);
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
                [$shortName, $i] = $this->parseClassName($tokens, $i + 1, $count);
                if ($shortName === null) {
                    continue;
                }

                [$extends, $implements, $i] = $this->parseInheritance($tokens, $i, $count, $namespace, $useMap);

                $traits = [];
                if ($token[0] === T_CLASS) {
                    [$traits, $i] = $this->parseTraitsInClassBody($tokens, $i, $count, $namespace, $useMap);
                }

                $fqcn = $this->buildFqcn($shortName, $namespace, $useMap);

                $classes[] = [
                    'class'      => $fqcn,
                    'file'       => $path,
                    'namespace'  => $namespace,
                    'short_name' => $shortName,
                    'extends'    => $extends,
                    'implements' => $implements,
                    'traits'     => $traits,
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
    private function parseInheritance(array $tokens, int $index, int $count, string $namespace, array $useMap = []): array
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
                        $extends = $this->buildFqcn($name, $namespace, $useMap);
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
                            $implements[] = $this->buildFqcn($name, $namespace, $useMap);
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
     * Parse trait use list right after class opening brace (use Trait1, Trait2;).
     *
     * @param array<int,mixed> $tokens
     *
     * @return array{0: array<int,string>, 1: int}
     */
    private function parseTraitsInClassBody(array $tokens, int $index, int $count, string $namespace, array $useMap = []): array
    {
        $traits = [];

        while ($index < $count) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                $index++;
                continue;
            }

            if (is_array($token) && $token[0] === T_USE) {
                $index++;

                while ($index < $count) {
                    $t = $tokens[$index];

                    if (is_array($t) && $t[0] === T_WHITESPACE) {
                        $index++;
                        continue;
                    }

                    if (!is_array($t) && $t === ';') {
                        $index++;
                        break 2;
                    }

                    if (!is_array($t) && $t === '{') {
                        $index = $this->skipBalancedBraces($tokens, $index, $count);
                        continue;
                    }

                    if (is_array($t) && ($t[0] === T_FUNCTION || $t[0] === T_CONST)) {
                        $index = $this->skipUntilSemicolon($tokens, $index, $count);
                        break;
                    }

                    [$name, $index] = $this->collectQualifiedName($tokens, $index, $count);
                    if ($name !== '') {
                        $traits[] = $this->buildFqcn($name, $namespace, $useMap);
                    }

                    $t = $index < $count ? $tokens[$index] : null;
                    if (!is_array($t) && $t === ',') {
                        $index++;
                    }
                }
                continue;
            }

            if (!is_array($token) && $token === '}') {
                break;
            }

            $index++;
        }

        return [$traits, $index];
    }

    /**
     * Skip from opening '{' to the matching '}'.
     *
     * @param array<int,mixed> $tokens
     */
    private function skipBalancedBraces(array $tokens, int $index, int $count): int
    {
        $depth = 0;

        while ($index < $count) {
            $t = $tokens[$index];

            if (!is_array($t)) {
                if ($t === '{') {
                    $depth++;
                } elseif ($t === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return $index + 1;
                    }
                }
            }

            $index++;
        }

        return $index;
    }

    /**
     * Advance index until after the next semicolon.
     *
     * @param array<int,mixed> $tokens
     */
    private function skipUntilSemicolon(array $tokens, int $index, int $count): int
    {
        while ($index < $count) {
            if (!is_array($tokens[$index]) && $tokens[$index] === ';') {
                return $index + 1;
            }
            $index++;
        }
        return $index;
    }

    /**
     * Parse file-level use statements (short name or alias => FQCN).
     *
     * @param array<int,mixed> $tokens
     * @return array<string,string>
     */
    private function parseFileLevelUseStatements(array $tokens, int $count): array
    {
        $uses  = [];
        $depth = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth = max(0, $depth - 1);
                }
                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            if ($token[0] !== T_USE) {
                continue;
            }

            $i++;

            // Skip whitespace
            while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }

            if ($i >= $count) {
                break;
            }

            // Group use: use Foo\Bar\{A, B as C};
            if (!is_array($tokens[$i]) && $tokens[$i] === '{') {
                // Unexpected form, skip; we don't expect leading '{' without base namespace.
                continue;
            }

            // Collect base namespace (for group use or simple use)
            [$base, $i] = $this->collectQualifiedName($tokens, $i, $count);
            if ($base === '') {
                continue;
            }

            $baseTrimmed = rtrim($base, '\\');

            // Skip whitespace
            while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }

            if ($i < $count && !is_array($tokens[$i]) && $tokens[$i] === '{') {
                // Group use
                $i++;
                while ($i < $count) {
                    // Skip whitespace
                    while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                        $i++;
                    }
                    if ($i >= $count) {
                        break;
                    }

                    if (!is_array($tokens[$i]) && $tokens[$i] === '}') {
                        $i++;
                        break;
                    }

                    // Name part (e.g. A or B)
                    [$namePart, $i] = $this->collectQualifiedName($tokens, $i, $count);
                    if ($namePart === '') {
                        break;
                    }

                    $alias = $namePart;

                    // Skip whitespace
                    while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                        $i++;
                    }

                    // Optional \"as Alias\"
                    if ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_AS) {
                        $i++;
                        while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                            $i++;
                        }
                        if ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                            $alias = $tokens[$i][1];
                            $i++;
                        }
                    }

                    $fqcn          = $baseTrimmed . '\\\\' . $namePart;
                    $uses[$alias] = ltrim($fqcn, '\\\\');

                    // Move past comma or end of group
                    while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                        $i++;
                    }
                    if ($i < $count && !is_array($tokens[$i]) && $tokens[$i] === ',') {
                        $i++;
                        continue;
                    }
                }

                // Skip until semicolon
                while ($i < $count && (!is_array($tokens[$i]) || $tokens[$i] !== ';')) {
                    if (!is_array($tokens[$i]) && $tokens[$i] === ';') {
                        break;
                    }
                    $i++;
                }

                continue;
            }

            // Simple use: use Foo\Bar\Baz; or with alias: use Foo\Bar\Baz as Alias;
            $alias = $baseTrimmed !== '' ? basename(str_replace('\\\\', '/', $baseTrimmed)) : '';

            // Skip whitespace
            while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }

            if ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_AS) {
                $i++;
                while ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                if ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $alias = $tokens[$i][1];
                }
            }

            if ($alias !== '') {
                $uses[$alias] = ltrim($baseTrimmed, '\\\\');
            }
        }

        return $uses;
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
     * Build a FQCN taking current namespace and file-level use imports into account.
     *
     * @param array<string,string> $useMap short name or alias => FQCN (without leading backslash)
     */
    private function buildFqcn(string $name, string $namespace, array $useMap = []): string
    {
        $original = $name;
        $trimmed  = ltrim($name, '\\');

        if ($trimmed === '') {
            return '';
        }

        // Fully-qualified name (starts with \) — do not prepend namespace.
        if (str_starts_with($original, '\\')) {
            return $trimmed;
        }

        // Simple name without namespace separators: try use-import first.
        if (!str_contains($trimmed, '\\')) {
            if (isset($useMap[$trimmed])) {
                return ltrim($useMap[$trimmed], '\\');
            }

            return $namespace === '' ? $trimmed : $namespace . '\\' . $trimmed;
        }

        // Relative name with namespace separators: resolve first segment via use-import if present.
        $parts = explode('\\', $trimmed, 2);
        $first = $parts[0];
        $rest  = $parts[1] ?? '';

        if (isset($useMap[$first])) {
            $base = rtrim(ltrim($useMap[$first], '\\'), '\\');
            return $rest === '' ? $base : $base . '\\' . $rest;
        }

        return $namespace === '' ? $trimmed : $namespace . '\\' . $trimmed;
    }

    /**
     * Normalize a class name for comparison (without leading slash, lowercased).
     */
    private function normalizeClassName(string $name): string
    {
        return strtolower(ltrim($name, '\\'));
    }
}

