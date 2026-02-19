<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Logging\ImpersonateLogger;
use PHPUnit\Framework\TestCase;

final class ImpersonateLoggerTest extends TestCase
{
    public function testWritesFormattedLineWithoutSensitiveData(): void
    {
        $file = sys_get_temp_dir() . '/impersonate-log-' . bin2hex(random_bytes(6)) . '.log';
        $logger = new ImpersonateLogger($file);

        $logger->log('NOTICE', [
            'event' => 'impersonate_start',
            'actor' => 'admin',
            'target' => 'user1',
            'result' => 'ok',
            'reason' => 'manual',
            'ip' => '127.0.0.1',
            'ua' => 'phpunit',
            'token' => 'must-not-appear',
            'nonce' => 'must-not-appear',
        ]);

        $content = (string)file_get_contents($file);
        self::assertStringContainsString('impersonate.NOTICE:', $content);
        self::assertStringContainsString('event=impersonate_start', $content);
        self::assertStringContainsString('actor=admin', $content);
        self::assertStringContainsString('target=user1', $content);
        self::assertStringContainsString('result=ok', $content);
        self::assertStringContainsString('reason=manual', $content);
        self::assertStringNotContainsString('must-not-appear', $content);

        @unlink($file);
    }
}

