<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Logging;

final class ImpersonateLogger
{
    private const DIR_MODE = 0750;

    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function log(string $level, array $context): void
    {
        $this->ensureDirectoryExists();
        $line = sprintf(
            '[%s] impersonate.%s: %s [] []',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $this->formatContext($context)
        );

        file_put_contents($this->file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, self::DIR_MODE, true);
        }
    }

    private function formatContext(array $context): string
    {
        $allowed = ['event', 'actor', 'target', 'result', 'reason', 'ip', 'ua', 'mode'];
        $parts = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $context) || $context[$key] === null || $context[$key] === '') {
                continue;
            }

            $parts[] = $key . '=' . $this->normalizeValue($context[$key]);
        }

        return implode(' ', $parts);
    }

    /**
     * Keep values single-line for log parsing/tailing.
     */
    private function normalizeValue($value): string
    {
        $text = (string)$value;
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);

        return trim($text);
    }
}
