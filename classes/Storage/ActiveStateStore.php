<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Storage;

final class ActiveStateStore
{
    private const DIR_MODE = 0700;

    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');
    }

    public function set(string $actor, array $state): void
    {
        $this->ensureDirectoryExists();
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        file_put_contents($this->getPath($actor), $json, LOCK_EX);
    }

    public function get(string $actor): ?array
    {
        $path = $this->getPath($actor);
        if (!is_file($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $state = json_decode($json, true);

        return is_array($state) ? $state : null;
    }

    public function clear(string $actor): void
    {
        $path = $this->getPath($actor);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, self::DIR_MODE, true);
        }
    }

    private function getPath(string $actor): string
    {
        return $this->directory . '/active-' . md5($actor) . '.json';
    }
}
