<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Security;

final class ImpersonationPolicy
{
    public function canImpersonateSelf(bool $splitEnabled, bool $hasSelfPermission, bool $isSuper): bool
    {
        if (!$splitEnabled) {
            return false;
        }

        return $hasSelfPermission || $isSuper;
    }

    public function canImpersonateUser(
        bool $splitEnabled,
        bool $hasUsersPermission,
        bool $isSuper,
        bool $targetIsAdmin,
        bool $targetEnabled,
        bool $targetLoginEnabled,
        bool $allowLoginDisabledTargets
    ): bool {
        if (!$splitEnabled) {
            return false;
        }

        if (!$targetEnabled) {
            return false;
        }

        if ($targetIsAdmin) {
            return false;
        }

        if (!$targetLoginEnabled && !$allowLoginDisabledTargets) {
            return false;
        }

        return $hasUsersPermission || $isSuper;
    }

    public function canStop(bool $splitEnabled, bool $hasStopPermission, bool $isSuper): bool
    {
        if (!$splitEnabled) {
            return false;
        }

        return $hasStopPermission || $isSuper;
    }
}
