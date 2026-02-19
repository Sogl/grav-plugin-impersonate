<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Security;

/**
 * Nonce validator for admin task requests.
 *
 * Key principle: the plugin should not rely on Grav Admin always validating the nonce
 * "somewhere earlier" in the request lifecycle — this can change across updates.
 */
final class AdminTaskRequestValidator
{
    public function isNonceValid(?string $nonce, callable $verifier): bool
    {
        if (!is_string($nonce) || trim($nonce) === '') {
            return false;
        }

        return (bool)$verifier($nonce, 'admin-form');
    }
}

