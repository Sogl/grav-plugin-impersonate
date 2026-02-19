<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Security;

final class FrontendStopRequestValidator
{
    public function isMethodAllowed(string $requestMethod): bool
    {
        return strtoupper($requestMethod) === 'POST';
    }

    public function isNonceValid(?string $nonce, callable $verifier): bool
    {
        if (!is_string($nonce) || trim($nonce) === '') {
            return false;
        }

        return (bool)$verifier($nonce, 'impersonate-stop');
    }
}
