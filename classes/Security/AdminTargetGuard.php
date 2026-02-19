<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Security;

/**
 * Defense-in-depth guard for admin targets.
 *
 * Even if admin-side checks regress, frontend activation should still refuse to impersonate
 * admin accounts by default. "self" mode is explicitly allowed.
 */
final class AdminTargetGuard
{
    public function canActivate(string $mode, bool $allowAdminTargets, bool $targetIsAdmin): bool
    {
        if ($mode === 'self') {
            return true;
        }

        if ($targetIsAdmin && !$allowAdminTargets) {
            return false;
        }

        return true;
    }
}

