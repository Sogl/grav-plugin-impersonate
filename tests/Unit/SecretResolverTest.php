<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Security\SecretResolver;
use PHPUnit\Framework\TestCase;

final class SecretResolverTest extends TestCase
{
    public function testPrefersEnvSecret(): void
    {
        $resolver = new SecretResolver();

        $secret = $resolver->resolve('this-is-a-very-strong-secret', 'another-strong-secret');
        self::assertSame('this-is-a-very-strong-secret', $secret);
    }

    public function testFallsBackToSystemSalt(): void
    {
        $resolver = new SecretResolver();

        $secret = $resolver->resolve('', 'this-is-a-strong-system-salt');
        self::assertSame('this-is-a-strong-system-salt', $secret);
    }

    public function testReturnsNullIfSecretsMissing(): void
    {
        $resolver = new SecretResolver();

        self::assertNull($resolver->resolve('', ''));
        self::assertNull($resolver->resolve(null, null));
    }

    public function testReturnsNullForWeakSecret(): void
    {
        $resolver = new SecretResolver();

        self::assertNull($resolver->resolve('short', ''));
    }
}
