<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Security;

final class SecretResolver
{
    public function resolve(?string $envSecret, ?string $systemSalt): ?string
    {
        $envSecret = $this->normalize($envSecret);
        if ($envSecret !== null && $this->isStrongEnough($envSecret)) {
            return $envSecret;
        }

        $systemSalt = $this->normalize($systemSalt);
        if ($systemSalt !== null && $this->isStrongEnough($systemSalt)) {
            return $systemSalt;
        }

        return null;
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function isStrongEnough(string $secret): bool
    {
        return strlen($secret) >= 12;
    }
}
