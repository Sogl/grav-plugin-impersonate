<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Service;

use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Plugin\Impersonate\Logging\ImpersonateLogger;
use Grav\Plugin\Impersonate\Security\SecretResolver;
use Grav\Plugin\Impersonate\Security\TokenSigner;
use Grav\Plugin\Impersonate\Storage\ActiveStateStore;
use Grav\Plugin\Impersonate\Storage\FileTokenStore;

class Impersonator
{
    /** @var Grav */
    protected $grav;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    public function isSplitEnabled(): bool
    {
        return (bool)$this->grav['config']->get('system.session.split', false);
    }

    public function iconClass(string $key, string $default): string
    {
        $value = trim((string)$this->grav['config']->get('plugins.impersonate.' . $key, ''));
        return $value !== '' ? $value : $default;
    }

    public function isSiteLoginEnabled($user): bool
    {
        $value = null;
        if (is_object($user) && method_exists($user, 'get')) {
            $value = $user->get('access.site.login');
        } elseif (is_array($user)) {
            $value = $user['access']['site']['login'] ?? null;
        }

        if ($value === null || $value === '') {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return filter_var((string)$value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function isAdminAccount($user): bool
    {
        if (!($user instanceof UserInterface)) {
            return false;
        }

        // authorize() depends on authenticated flag for some user implementations.
        $original = $user->authenticated;
        $user->authenticated = true;
        $isAdmin = (bool)$user->authorize('admin.login') || (bool)$user->authorize('admin.super');
        $user->authenticated = $original;

        return $isAdmin;
    }

    public function resolveTargetUsername(): ?string
    {
        $post = (array)$this->grav['uri']->post();
        // Never read the target from query string: even if nonce validation regresses,
        // this keeps CSRF surface much smaller (no "click a link to impersonate").
        $target = (string)($post['target'] ?? $this->grav['uri']->param('target') ?? '');
        if ($target !== '') {
            return $target;
        }

        $admin = $this->grav['admin'] ?? null;
        if ($admin && isset($admin->route) && is_string($admin->route) && $admin->route !== '') {
            return $admin->route;
        }

        return null;
    }

    public function issueStartUrl(string $target, string $mode): string
    {
        $actor = (string)$this->grav['user']->username;
        $payload = [
            'action' => 'start',
            'mode' => $mode,
            'actor_user' => $actor,
            'target_user' => $target,
            'nonce' => bin2hex(random_bytes(16)),
            'iat' => time(),
            'exp' => time() + (int)$this->grav['config']->get('plugins.impersonate.token_ttl_seconds', 45),
            'redirect' => $this->safeRedirect((string)$this->grav['config']->get('plugins.impersonate.default_redirect', '/')),
        ];

        $token = $this->signer()->issue($payload);
        $this->tokenStore()->purgeExpired(time());
        $this->tokenStore()->save(hash('sha256', $token), [
            'actor' => $actor,
            'target' => $target,
            'nonce' => $payload['nonce'],
            'action' => 'start',
            'mode' => $mode,
            'exp' => $payload['exp'],
            'used_at' => null,
            'created_at' => time(),
        ]);

        return $this->frontendAbsoluteUrl('/impersonate/' . rawurlencode($token));
    }

    public function issueStopUrl(string $actor): string
    {
        $payload = [
            'action' => 'stop',
            'actor_user' => $actor,
            'nonce' => bin2hex(random_bytes(16)),
            'iat' => time(),
            'exp' => time() + (int)$this->grav['config']->get('plugins.impersonate.token_ttl_seconds', 45),
            'redirect' => $this->safeRedirect((string)$this->grav['config']->get('plugins.impersonate.default_redirect', '/')),
        ];

        $token = $this->signer()->issue($payload);
        $this->tokenStore()->purgeExpired(time());
        $this->tokenStore()->save(hash('sha256', $token), [
            'actor' => $actor,
            'target' => '',
            'nonce' => $payload['nonce'],
            'action' => 'stop',
            'mode' => 'stop',
            'exp' => $payload['exp'],
            'used_at' => null,
            'created_at' => time(),
        ]);

        return $this->frontendAbsoluteUrl('/impersonate/stop/' . rawurlencode($token));
    }

    public function consumeToken(string $token, string $expectedAction): ?array
    {
        $token = rawurldecode($token);
        $payload = $this->signer()->validate($token);
        if (!$payload) {
            return null;
        }

        if (($payload['action'] ?? '') !== $expectedAction) {
            return null;
        }

        $hash = hash('sha256', $token);
        $record = $this->tokenStore()->consume($hash, time());
        if (!$record) {
            return null;
        }

        if (($record['nonce'] ?? '') !== ($payload['nonce'] ?? '')) {
            return null;
        }

        return $payload;
    }

    public function activeStore(): ActiveStateStore
    {
        static $store;
        if ($store instanceof ActiveStateStore) {
            return $store;
        }

        $dir = $this->grav['locator']->findResource('user://data/impersonate/active', true, true);
        $store = new ActiveStateStore($dir);

        return $store;
    }

    public function logEvent(string $event, string $actor, string $target, string $result, string $reason): void
    {
        if (!(bool)$this->grav['config']->get('plugins.impersonate.log_events', true)) {
            return;
        }

        $ip = Uri::ip();
        if ($ip === 'UNKNOWN') {
            $ip = '';
        }

        $this->logger()->log('NOTICE', [
            'event' => $event,
            'actor' => $actor,
            'target' => $target,
            'result' => $result,
            'reason' => $reason,
            'ip' => $ip,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }

    public function frontendAbsoluteUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $customBase = rtrim((string)$this->grav['config']->get('system.custom_base_url', ''), '/');
        if ($customBase !== '') {
            return $customBase . $path;
        }

        return Utils::url($path, true);
    }

    public function safeRedirect(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        if ($path[0] !== '/' || strpos($path, '//') === 0) {
            return '/';
        }

        return $path;
    }

    private function tokenStore(): FileTokenStore
    {
        static $store;
        if ($store instanceof FileTokenStore) {
            return $store;
        }

        $dir = $this->grav['locator']->findResource('user://data/impersonate/tokens', true, true);
        $store = new FileTokenStore($dir);

        return $store;
    }

    private function signer(): TokenSigner
    {
        static $signer;
        if ($signer instanceof TokenSigner) {
            return $signer;
        }

        $resolver = new SecretResolver();
        $secret = $resolver->resolve(
            getenv('IMPERSONATE_TOKEN_SECRET') ?: null,
            (string)$this->grav['config']->get('security.salt', '')
        );

        if (!$secret) {
            throw new \RuntimeException('Unable to resolve impersonation token secret');
        }

        $signer = new TokenSigner($secret);

        return $signer;
    }

    private function logger(): ImpersonateLogger
    {
        static $logger;
        if ($logger instanceof ImpersonateLogger) {
            return $logger;
        }

        $file = $this->grav['locator']->findResource('log://impersonate.log', true, true);
        $logger = new ImpersonateLogger($file);

        return $logger;
    }
}
