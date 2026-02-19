<?php

declare(strict_types=1);

use Grav\Plugin\Impersonate\Admin\LogTailReader;
use PHPUnit\Framework\TestCase;

final class LogTailReaderTest extends TestCase
{
    public function testReturnsEmptyForMissingFile(): void
    {
        $reader = new LogTailReader();
        $path = sys_get_temp_dir() . '/impersonate-missing-' . bin2hex(random_bytes(4)) . '.log';

        $this->assertSame('', $reader->read($path));
    }

    public function testReadsWholeFileWhenSmallEnough(): void
    {
        $reader = new LogTailReader();
        $path = sys_get_temp_dir() . '/impersonate-small-' . bin2hex(random_bytes(4)) . '.log';
        file_put_contents($path, "line-1\nline-2\n");

        $this->assertSame("line-1\nline-2\n", $reader->read($path, 1024));
        @unlink($path);
    }

    public function testReadsTailAndAddsTruncatedPrefix(): void
    {
        $reader = new LogTailReader();
        $path = sys_get_temp_dir() . '/impersonate-big-' . bin2hex(random_bytes(4)) . '.log';
        file_put_contents($path, '0123456789ABCDEFGHIJ');

        $result = $reader->read($path, 10);

        $this->assertStringStartsWith("[... log truncated ...]\n", $result);
        $this->assertStringEndsWith('ABCDEFGHIJ', $result);
        @unlink($path);
    }
}
