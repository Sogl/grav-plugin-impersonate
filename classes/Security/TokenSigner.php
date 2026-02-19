<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Security;

final class TokenSigner
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function issue(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode token payload');
        }

        $encodedPayload = $this->base64UrlEncode($json);
        $signature = hash_hmac('sha256', $encodedPayload, $this->secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        return $encodedPayload . '.' . $encodedSignature;
    }

    public function validate(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedPayload, $this->secret, true)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return null;
        }

        $json = $this->base64UrlDecode($encodedPayload);
        if ($json === null) {
            return null;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = $payload['exp'] ?? null;
        if (!is_int($exp) || $exp < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $input): ?string
    {
        $remainder = strlen($input) % 4;
        if ($remainder !== 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($input, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}

