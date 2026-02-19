<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Security\TokenSigner;
use PHPUnit\Framework\TestCase;

final class TokenSignerTest extends TestCase
{
    public function testGeneratesAndValidatesToken(): void
    {
        $signer = new TokenSigner('test-secret');

        $token = $signer->issue([
            'actor_user' => 'admin',
            'target_user' => 'user1',
            'nonce' => 'abc123',
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        self::assertNotSame('', $token);
        self::assertIsArray($signer->validate($token));
    }

    public function testRejectsExpiredToken(): void
    {
        $signer = new TokenSigner('test-secret');
        $token = $signer->issue([
            'actor_user' => 'admin',
            'target_user' => 'user1',
            'nonce' => 'expired1',
            'iat' => time() - 120,
            'exp' => time() - 60,
        ]);

        self::assertNull($signer->validate($token));
    }
}

