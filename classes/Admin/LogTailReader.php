<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Admin;

final class LogTailReader
{
    public function read(string $file, int $maxBytes = 200000): string
    {
        if (!is_file($file)) {
            return '';
        }

        $size = (int)filesize($file);
        if ($size <= 0) {
            return '';
        }

        if ($size <= $maxBytes) {
            $content = file_get_contents($file);
            return is_string($content) ? $content : '';
        }

        $content = file_get_contents($file, false, null, $size - $maxBytes);
        if (!is_string($content)) {
            return '';
        }

        return "[... log truncated ...]\n" . $content;
    }
}
