<?php

declare(strict_types=1);

use MB\Filesystem\Exceptions\IOException;

final class FileNodeTest extends FilesystemTestCase
{
    public function testFileUpdateAcceptsAtomicParameter(): void
    {
        $fs = $this->fs();
        $fs->put('node.txt', 'x');
        $file = $fs->file('node.txt');
        $file->update(static fn (string $c) => $c . 'y', false);
        $this->assertSame('xy', $fs->content('node.txt'));
    }

    public function testFileNodeContentAndLinesAndUpdate(): void
    {
        $fs = $this->fs();
        $fs->put('lines.txt', "line1\nline2\nline3");
        $file = $fs->file('lines.txt');

        $this->assertSame("line1\nline2\nline3", $file->content());

        $lines = iterator_to_array($file->lines());
        $this->assertCount(3, $lines);
        $this->assertSame("line1\n", $lines[0]);
        $this->assertSame("line2\n", $lines[1]);
        $this->assertSame('line3', $lines[2]);

        $file->update(static fn (string $c) => $c . "\nline4");
        $this->assertStringEndsWith('line4', $fs->content('lines.txt'));
    }

    public function testFileNodeLineByNumber(): void
    {
        $fs = $this->fs();
        $fs->put('numbered.txt', "first\nsecond\nthird");
        $file = $fs->file('numbered.txt');

        $this->assertSame("first\n", $file->line(1));
        $this->assertSame("second\n", $file->line(2));
        $this->assertSame('third', $file->line(3));

        $this->expectException(IOException::class);
        $this->expectExceptionMessage('Line 5 does not exist');
        $file->line(5);
    }

    public function testFileNodeLineRequiresPositiveNumber(): void
    {
        $fs = $this->fs();
        $fs->put('one.txt', 'x');
        $file = $fs->file('one.txt');

        $this->expectException(IOException::class);
        $this->expectExceptionMessage('Line number must be >= 1');
        $file->line(0);
    }

    public function testFileNodeWriteAndDelete(): void
    {
        $fs = $this->fs();
        $fs->put('writable.txt', 'old');
        $file = $fs->file('writable.txt');
        $file->write('new');
        $this->assertSame('new', $file->content());
        $file->delete();
        $this->assertFalse($fs->exists('writable.txt'));
    }
}
