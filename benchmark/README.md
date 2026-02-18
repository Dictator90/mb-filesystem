# MB Filesystem Benchmark

Run performance benchmarks for the filesystem package and get statistics (time, memory, ops/sec).

## Run

```bash
composer benchmark
```

Or directly:

```bash
php benchmark/run.php
```

## Output

- **time_ms** — duration of each operation in milliseconds
- **peak MB** — memory delta during the operation (or absolute peak for the run)
- **ops / ops/s** — number of items processed and throughput (where applicable)

## Configuration (environment variables)

| Variable | Default | Description |
|----------|---------|-------------|
| `BENCH_FILES` | 1000 | Target number of files in the tree |
| `BENCH_TREE_DEPTH` | 5 | Depth of the directory tree |
| `BENCH_LARGE_MB` | 2.0 | Size of the large file in MB |
| `BENCH_ITERATIONS` | 1 | Number of iterations (reserved for future use) |

Example (lighter run):

```bash
BENCH_FILES=200 BENCH_LARGE_MB=0.5 composer benchmark
```

## Scenarios

1. **files(recursive)** — list all files in a tree
2. **directories(recursive)** — list all directories
3. **file() metadata** — get `File` node for many paths
4. **get()** — read content of many small files
5. **File::update() atomic** — read-modify-write via File node (temp + rename)
6. **updateContent(path, updater, false)** — non-atomic read-modify-write by path (direct write, faster)
7. **substring()** — content search over PHP files
8. **File::content()** — load full large file into memory
9. **File::lines()** — iterate large file line-by-line (streaming)
10. **copyDirectoryRecursive** — recursive copy
11. **deleteDirectory(..., true)** — recursive delete

Temp directory is created under `sys_get_temp_dir()` and removed after the run.
