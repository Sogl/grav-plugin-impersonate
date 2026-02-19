<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Storage;

final class FileTokenStore
{
    private const DIR_MODE = 0700;

    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');
    }

    public function save(string $tokenHash, array $record): void
    {
        $this->ensureDirectoryExists();
        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        file_put_contents($this->getPath($tokenHash), $json, LOCK_EX);
    }

    public function get(string $tokenHash): ?array
    {
        $path = $this->getPath($tokenHash);
        if (!is_file($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    public function markUsed(string $tokenHash, int $usedAt): void
    {
        $record = $this->get($tokenHash);
        if (!$record) {
            return;
        }

        $record['used_at'] = $usedAt;
        $this->save($tokenHash, $record);
    }

    /**
     * Atomically read and mark token as used.
     * Returns updated record when token is consumable, null otherwise.
     */
    public function consume(string $tokenHash, int $now): ?array
    {
        $path = $this->getPath($tokenHash);
        if (!is_file($path)) {
            return null;
        }

        $fp = @fopen($path, 'c+');
        if (!$fp) {
            return null;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return null;
        }

        rewind($fp);
        $json = stream_get_contents($fp);
        $record = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($record)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return null;
        }

        if (($record['used_at'] ?? null) !== null) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return null;
        }

        $exp = $record['exp'] ?? 0;
        if (!is_int($exp) || $exp < $now) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return null;
        }

        $record['used_at'] = $now;
        $updated = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($updated === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return null;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $updated);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $record;
    }

    public function purgeExpired(int $now): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }

            $record = json_decode($json, true);
            if (!is_array($record)) {
                @unlink($file);
                continue;
            }

            $exp = $record['exp'] ?? 0;
            if (!is_int($exp) || $exp < $now) {
                @unlink($file);
            }
        }
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, self::DIR_MODE, true);
        }
    }

    private function getPath(string $tokenHash): string
    {
        return $this->directory . '/' . $tokenHash . '.json';
    }
}
