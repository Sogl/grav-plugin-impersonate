<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Tests\Unit;

use Grav\Plugin\Impersonate\Security\ImpersonationPolicy;
use PHPUnit\Framework\TestCase;

final class ImpersonationPolicyTest extends TestCase
{
    public function testSelfAllowedWithSplitAndPermission(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertTrue($policy->canImpersonateSelf(true, true, false));
    }

    public function testSelfDeniedWhenSplitDisabled(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertFalse($policy->canImpersonateSelf(false, true, true));
    }

    public function testUserImpersonationAllowedForEnabledNonAdmin(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertTrue($policy->canImpersonateUser(true, true, false, false, true, true, false));
    }

    public function testUserImpersonationDeniedWhenSplitDisabled(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertFalse($policy->canImpersonateUser(false, true, true, false, true, true, true));
    }

    public function testUserImpersonationDeniedForAdminTarget(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertFalse($policy->canImpersonateUser(true, true, true, true, true, true, false));
    }

    public function testUserImpersonationDeniedForDisabledTarget(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertFalse($policy->canImpersonateUser(true, true, true, false, false, true, false));
    }

    public function testUserImpersonationDeniedForLoginDisabledByDefault(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertFalse($policy->canImpersonateUser(true, true, true, false, true, false, false));
    }

    public function testUserImpersonationAllowedForLoginDisabledIfOverrideEnabled(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertTrue($policy->canImpersonateUser(true, true, true, false, true, false, true));
    }

    public function testStopAllowedForSuperUser(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertTrue($policy->canStop(true, false, true));
    }

    public function testStopDeniedWhenSplitDisabled(): void
    {
        $policy = new ImpersonationPolicy();

        self::assertFalse($policy->canStop(false, true, true));
    }
}
