<?php

declare(strict_types=1);

/**
 * Benchmark script for mb4it/filesystem.
 *
 * Run: php benchmark/run.php
 * Or:  composer benchmark
 *
 * Environment: BENCH_FILES, BENCH_TREE_DEPTH, BENCH_LARGE_MB to scale (optional).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use MB\Filesystem\Filesystem;

// --- Configuration (env or defaults) ---
$numFiles = (int) (getenv('BENCH_FILES') ?: 1000);
$treeDepth = (int) (getenv('BENCH_TREE_DEPTH') ?: 5);
$largeFileMb = (float) (getenv('BENCH_LARGE_MB') ?: 2.0);
$iterations = max(1, (int) (getenv('BENCH_ITERATIONS') ?: 1));

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mb_fs_bench_' . uniqid();
$fs = new Filesystem($tmpDir);

$stats = [];

function measure(string $name, callable $fn): array
{
    $memBefore = memory_get_peak_usage(true);
    $t0 = hrtime(true); // PHP 8: int (nanoseconds), PHP 7: [sec, nsec]
    $fn();
    $t1 = hrtime(true);
    $memAfter = memory_get_peak_usage(true);
    $nanos = is_array($t0) ? ($t1[0] - $t0[0]) * 1e9 + ($t1[1] - $t0[1]) : $t1 - $t0;
    return [
        'name' => $name,
        'time_ms' => $nanos / 1e6,
        'memory_peak_mb' => ($memAfter - $memBefore) / (1024 * 1024),
        'memory_absolute_mb' => $memAfter / (1024 * 1024),
    ];
}

function formatStat(array $s, ?int $count = null): string
{
    $out = sprintf(
        "  %-40s  %10.2f ms  %8.2f MB peak",
        $s['name'],
        $s['time_ms'],
        $s['memory_peak_mb']
    );
    if ($count !== null && $s['time_ms'] > 0) {
        $opsPerSec = $count / ($s['time_ms'] / 1000);
        $out .= sprintf("  (%d ops, %.0f ops/s)", $count, $opsPerSec);
    }
    return $out;
}

echo "MB Filesystem Benchmark\n";
echo str_repeat('=', 80) . "\n";
echo "Config: files={$numFiles}, tree_depth={$treeDepth}, large_file_mb={$largeFileMb}, iterations={$iterations}\n";
echo "Temp dir: {$tmpDir}\n";
echo str_repeat('=', 80) . "\n\n";

// --- Setup: create tree and large file ---
echo "Setting up...\n";
$fs->makeDirectory('tree');
$created = 0;
$dirsPerLevel = 3;
$filesPerDir = (int) ceil($numFiles / (pow($dirsPerLevel, $treeDepth)));
buildTree($fs, 'tree', $treeDepth, $dirsPerLevel, $filesPerDir, 0, $created);
$totalFilesCreated = $created;

// Some PHP files for grepPaths search
$needle = '__BENCH_NEEDLE__';
$fs->makeDirectory('phpsearch');
for ($i = 0; $i < 200; $i++) {
    $content = $i % 5 === 0 ? "<?php\n// " . $needle . " id={$i}\n" : "<?php\n// id={$i}\n";
    $fs->put("phpsearch/f{$i}.php", $content);
}

// Files for update() benchmark (atomic uses update_test, non-atomic uses update_test2)
$updateCount = 100;
$fs->makeDirectory('update_test');
$fs->makeDirectory('update_test2');
for ($i = 0; $i < $updateCount; $i++) {
    $fs->put("update_test/u{$i}.txt", "original content {$i}");
    $fs->put("update_test2/u{$i}.txt", "original content {$i}");
}

// Large file
$largePath = 'large.txt';
$chunk = str_repeat('x', 1000) . "\n";
$targetBytes = (int) ($largeFileMb * 1024 * 1024);
$written = 0;
while ($written < $targetBytes) {
    $fs->append($largePath, str_repeat($chunk, 100));
    $written += strlen($chunk) * 100;
}
echo "Created {$totalFilesCreated} files in tree, 200 PHP files, {$updateCount} update-test files, " . round($written / 1024 / 1024, 2) . " MB large file.\n\n";

// --- Benchmark: recursive files() ---
$r = measure('files(recursive)', function () use ($fs) {
    $fs->files('tree', true);
});
$r['count'] = $totalFilesCreated;
$stats[] = $r;

// --- Benchmark: recursive directories() ---
$dirCount = 0;
for ($d = 0, $n = 1; $d < $treeDepth; $d++) {
    $n *= $dirsPerLevel;
    $dirCount += $n;
}
$r = measure('directories(recursive)', function () use ($fs) {
    $fs->directories('tree', true);
});
$r['count'] = $dirCount;
$stats[] = $r;

// --- Benchmark: file() metadata (sample of files) ---
$fileList = $fs->files('tree', true);
$sampleSize = min(300, count($fileList));
$r = measure("file() metadata x{$sampleSize}", function () use ($fs, $fileList, $sampleSize) {
    for ($i = 0; $i < $sampleSize; $i++) {
        $fs->file($fileList[$i]);
    }
});
$r['count'] = $sampleSize;
$stats[] = $r;

// --- Benchmark: get() content (sample) ---
$r = measure("get() x{$sampleSize}", function () use ($fs, $fileList, $sampleSize) {
    for ($i = 0; $i < $sampleSize; $i++) {
            $fs->content($fileList[$i]);
    }
});
$r['count'] = $sampleSize;
$stats[] = $r;

// --- Benchmark: File::update() atomic (read-modify-write via File node) ---
$r = measure("File::update() atomic x{$updateCount}", function () use ($fs, $updateCount) {
    for ($i = 0; $i < $updateCount; $i++) {
        $file = $fs->file("update_test/u{$i}.txt");
        $file->update(fn (string $c) => $c . "\nupdated");
    }
});
$r['count'] = $updateCount;
$stats[] = $r;

// --- Benchmark: updateContent(..., false) non-atomic by path ---
$r = measure("updateContent(path, updater, false) x{$updateCount}", function () use ($fs, $updateCount) {
    for ($i = 0; $i < $updateCount; $i++) {
        $fs->updateContent("update_test2/u{$i}.txt", fn (string $c) => $c . "\nupdated", false);
    }
});
$r['count'] = $updateCount;
$stats[] = $r;

// --- Benchmark: grepPaths (content search) ---
$r = measure('grepPaths(phpsearch, needle)', function () use ($fs, $needle) {
    $fs->grepPaths('phpsearch', $needle, ['extensions' => ['php']]);
});
$r['count'] = 40; // 200/5
$stats[] = $r;

// --- Benchmark: large file content() ---
$r = measure('File::content() on large file', function () use ($fs, $largePath) {
    $file = $fs->file($largePath);
    strlen($file->content());
});
$r['count'] = 1;
$stats[] = $r;

// --- Benchmark: large file lines() iteration ---
$lineCount = 0;
$r = measure('File::lines() iteration (large file)', function () use ($fs, $largePath, &$lineCount) {
    $file = $fs->file($largePath);
    foreach ($file->lines() as $line) {
        $lineCount++;
    }
});
$r['count'] = $lineCount;
$stats[] = $r;

// --- Benchmark: copyDirectoryRecursive ---
$r = measure('copyDirectoryRecursive(tree -> tree_copy)', function () use ($fs) {
    $fs->copyDirectoryRecursive('tree', 'tree_copy');
});
$r['count'] = 1;
$stats[] = $r;

// --- Benchmark: deleteDirectory(recursive) ---
$r = measure('deleteDirectory(tree_copy, true)', function () use ($fs) {
    $fs->deleteDirectory('tree_copy', true);
});
$r['count'] = 1;
$stats[] = $r;

// --- Cleanup ---
$fs->deleteDirectory($tmpDir, true);

// --- Report ---
echo "RESULTS\n";
echo str_repeat('-', 80) . "\n";
foreach ($stats as $s) {
    echo formatStat($s, $s['count'] ?? null) . "\n";
}
echo str_repeat('-', 80) . "\n";
$totalTime = array_sum(array_column($stats, 'time_ms'));
$maxMem = max(array_column($stats, 'memory_absolute_mb'));
echo sprintf("  %-40s  %10.2f ms  %8.2f MB (max absolute)\n", 'TOTAL / MAX', $totalTime, $maxMem);
echo "\nDone.\n";

function buildTree(Filesystem $fs, string $dir, int $maxDepth, int $branches, int $filesPerDir, int $level, int &$created): void
{
    $fs->makeDirectory($dir);
    for ($f = 0; $f < $filesPerDir; $f++) {
        $fs->put("{$dir}/f_{$level}_{$f}.txt", "data {$level} {$f}");
        $created++;
    }
    if ($level < $maxDepth - 1) {
        for ($b = 0; $b < $branches; $b++) {
            buildTree($fs, "{$dir}/d{$b}", $maxDepth, $branches, $filesPerDir, $level + 1, $created);
        }
    }
}
