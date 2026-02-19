<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Security\FrontendStopTokenResolver;
use PHPUnit\Framework\TestCase;

final class FrontendStopTokenResolverTest extends TestCase
{
    public function testReturnsNullWhenTokenMissing(): void
    {
        $resolver = new FrontendStopTokenResolver();

        self::assertNull($resolver->resolveFromPost([]));
    }

    public function testReturnsNullWhenTokenNotString(): void
    {
        $resolver = new FrontendStopTokenResolver();

        self::assertNull($resolver->resolveFromPost(['token' => 123]));
        self::assertNull($resolver->resolveFromPost(['token' => ['x']]));
    }

    public function testTrimsAndReturnsToken(): void
    {
        $resolver = new FrontendStopTokenResolver();

        self::assertSame('abc.def', $resolver->resolveFromPost(['token' => '  abc.def  ']));
    }
}

