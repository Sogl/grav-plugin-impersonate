<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Storage\FileTokenStore;
use PHPUnit\Framework\TestCase;

final class FileTokenStoreTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/impersonate-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testStoresAndFetchesRecordByHash(): void
    {
        $store = new FileTokenStore($this->dir);
        $hash = hash('sha256', 'token-1');

        $store->save($hash, [
            'actor' => 'admin',
            'target' => 'user1',
            'exp' => time() + 60,
            'used_at' => null,
        ]);

        $record = $store->get($hash);
        self::assertIsArray($record);
        self::assertSame('admin', $record['actor']);
    }

    public function testMarksRecordAsUsed(): void
    {
        $store = new FileTokenStore($this->dir);
        $hash = hash('sha256', 'token-2');

        $store->save($hash, [
            'actor' => 'admin',
            'target' => 'user1',
            'exp' => time() + 60,
            'used_at' => null,
        ]);

        $store->markUsed($hash, 1234567890);
        $record = $store->get($hash);
        self::assertSame(1234567890, $record['used_at']);
    }

    public function testPurgesExpiredRecords(): void
    {
        $store = new FileTokenStore($this->dir);
        $expiredHash = hash('sha256', 'expired');
        $activeHash = hash('sha256', 'active');

        $store->save($expiredHash, [
            'actor' => 'admin',
            'target' => 'user1',
            'exp' => time() - 10,
            'used_at' => null,
        ]);
        $store->save($activeHash, [
            'actor' => 'admin',
            'target' => 'user2',
            'exp' => time() + 60,
            'used_at' => null,
        ]);

        $store->purgeExpired(time());

        self::assertNull($store->get($expiredHash));
        self::assertIsArray($store->get($activeHash));
    }

    public function testConsumeMarksTokenUsedOnlyOnce(): void
    {
        $store = new FileTokenStore($this->dir);
        $hash = hash('sha256', 'single-use-token');

        $store->save($hash, [
            'actor' => 'admin',
            'target' => 'user1',
            'nonce' => 'abc',
            'exp' => time() + 60,
            'used_at' => null,
        ]);

        $first = $store->consume($hash, time());
        self::assertIsArray($first);
        self::assertIsInt($first['used_at']);

        $second = $store->consume($hash, time());
        self::assertNull($second);
    }
}
