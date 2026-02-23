<?php

declare(strict_types=1);

use MB\Filesystem\Exceptions\IOException;

final class JsonTest extends FilesystemTestCase
{
    public function testJsonHelpers(): void
    {
        $fs = $this->fs();
        $path = 'config.json';

        $data = ['a' => 1, 'b' => 2];
        $fs->putJson($path, $data);

        $decoded = $fs->json($path, true);
        $this->assertSame($data, $decoded);

        $default = ['default' => true];
        $this->assertSame($default, $fs->json('missing.json', true, $default));

        $fs->updateJson($path, static function (array $config): array {
            $config['c'] = 3;
            return $config;
        });

        $updated = $fs->json($path, true);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $updated);
    }

    public function testInvalidJsonThrowsIOException(): void
    {
        $fs = $this->fs();
        $path = 'broken.json';

        $fs->put($path, '{invalid json');

        $this->expectException(IOException::class);
        $fs->json($path, true);
    }
}
