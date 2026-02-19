<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Security\AdminTargetGuard;
use PHPUnit\Framework\TestCase;

final class AdminTargetGuardTest extends TestCase
{
    public function testAllowsSelfModeEvenIfTargetIsAdmin(): void
    {
        $guard = new AdminTargetGuard();

        self::assertTrue($guard->canActivate('self', false, true));
    }

    public function testDeniesAdminTargetByDefault(): void
    {
        $guard = new AdminTargetGuard();

        self::assertFalse($guard->canActivate('user', false, true));
    }

    public function testAllowsAdminTargetWhenExplicitlyEnabled(): void
    {
        $guard = new AdminTargetGuard();

        self::assertTrue($guard->canActivate('user', true, true));
    }

    public function testAllowsNonAdminTarget(): void
    {
        $guard = new AdminTargetGuard();

        self::assertTrue($guard->canActivate('user', false, false));
    }
}

