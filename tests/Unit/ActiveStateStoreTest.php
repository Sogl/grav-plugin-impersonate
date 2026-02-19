<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Storage\ActiveStateStore;
use PHPUnit\Framework\TestCase;

final class ActiveStateStoreTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/impersonate-state-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testSetAndGetState(): void
    {
        $store = new ActiveStateStore($this->dir);
        $store->set('admin', ['target' => 'user1', 'started_at' => 123]);

        $state = $store->get('admin');
        self::assertIsArray($state);
        self::assertSame('user1', $state['target']);
    }

    public function testClearState(): void
    {
        $store = new ActiveStateStore($this->dir);
        $store->set('admin', ['target' => 'user1']);
        $store->clear('admin');

        self::assertNull($store->get('admin'));
    }
}

