<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Security;

/**
 * Resolves stop token from POST data.
 *
 * We intentionally do not accept tokens from URL path or query string to reduce leak surface
 * (referrer logs, copy/paste URLs, browser history) and to keep stop strictly POST-only.
 */
final class FrontendStopTokenResolver
{
    public function resolveFromPost(array $post): ?string
    {
        $token = $post['token'] ?? null;
        if (!is_string($token)) {
            return null;
        }

        $token = trim($token);

        return $token === '' ? null : $token;
    }
}

