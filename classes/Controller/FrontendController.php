<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Controller;

use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Plugin\Impersonate\Security\AdminTargetGuard;
use Grav\Plugin\Impersonate\Security\FrontendStopRequestValidator;
use Grav\Plugin\Impersonate\Security\FrontendStopTokenResolver;
use Grav\Plugin\Impersonate\Service\Impersonator;
use RocketTheme\Toolbox\Event\Event;

class FrontendController
{
    /** @var Grav */
    protected $grav;

    /** @var Impersonator */
    protected $impersonator;

    public function __construct(Grav $grav, Impersonator $impersonator)
    {
        $this->grav = $grav;
        $this->impersonator = $impersonator;
    }

    public function onPageInitialized(): void
    {
        $route = trim((string)$this->grav['uri']->route(), '/');
        if (strpos($route, 'impersonate/') !== 0) {
            return;
        }

        $parts = explode('/', $route);
        if (($parts[0] ?? '') !== 'impersonate') {
            return;
        }

        if (($parts[1] ?? '') === 'stop') {
            // Stop must be POST-only (with nonce + stop token in the request body).
            if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
                if ($this->wantsJsonResponse()) {
                    $this->jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
                }
                http_response_code(405);
                header('Allow: POST');
                exit;
            }

            $this->handleStopRoute();
            return;
        }

        if (($parts[1] ?? '') === 'status') {
            $this->handleStatusRoute();
            return;
        }

        if (isset($parts[1])) {
            $this->handleStartRoute((string)$parts[1]);
        }
    }

    public function onAssetsInitialized(): void
    {
        if (!$this->impersonator->isSplitEnabled() || !(bool)$this->grav['config']->get('plugins.impersonate.show_ui_button', true)) {
            return;
        }

        $isAdmin = false;
        // Check if admin plugin is active and we are in admin
        if (isset($this->grav['admin'])) {
             // If we are in admin context, we rely on AdminController for admin assets?
             // Actually, original code checked $this->isAdmin() which checks if current route is admin.
             // But existing code injected assets based on isAdmin check.
             // If we are in FrontendController, we might be called in Admin if we register for default events?
             // But usually controllers are separated.
             // Let's assume this method is called for both if we register it?
             // Wait, the original Plugin class registers onAssetsInitialized for both Admin and Frontend.
             // AdminController handles Admin side?
             // Original: onAssetsInitialized handles both cases.
             // We should split. AdminController should handle Admin assets, FrontendController frontend assets.
        }

        // Implementation for Frontend Assets
        // The isAdmin check in original plugin used internal Plugin::isAdmin() helper.
        // We can replicate logic:
        $adminRoute = (string)$this->grav['config']->get('plugins.admin.route', '/admin');
        $base = '/' . trim($adminRoute, '/');
        $uri = $this->grav['uri']->path();
        $isAdmin = substr($uri, 0, strlen($base)) === $base;

        if ($isAdmin) {
             // Admin assets injection.
             // Handled by AdminController?
             // Wait, if I split, I should move Admin assets logic to AdminController::onAssetsInitialized
             // and Frontend logic here.
             return;
        }

        $state = $this->getFrontendImpersonateState();
        $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            $this->grav['assets']->addInlineJs(
                'window.GravImpersonateSyncState = ' . $encoded . ';',
                ['group' => 'bottom', 'priority' => 101]
            );
        }

        $this->grav['assets']->addJs('plugin://impersonate/assets/frontend/impersonate-sync.js', [
            'group' => 'bottom',
            'loading' => 'defer',
            'priority' => 100,
        ]);
    }

    public function onUserLogout(Event $event): void
    {
        $session = $this->grav['session'];
        $impersonate = (array)($session->impersonate ?? []);
        if (!(bool)($impersonate['active'] ?? false)) {
            return;
        }

        $actor = (string)($impersonate['actor'] ?? '');
        $target = (string)($impersonate['target'] ?? '');
        if ($actor !== '') {
            $this->impersonator->activeStore()->clear($actor);
            $this->impersonator->logEvent('impersonate_stop', $actor, $target, 'ok', 'frontend_logout');
        }

        unset($session->impersonate);
    }

    public function onFrontendLogoutTask(Event $event): void
    {
        $this->clearFrontendImpersonateState('frontend_logout_task');
    }

    private function clearFrontendImpersonateState(string $reason): void
    {
        $session = $this->grav['session'];
        $impersonate = (array)($session->impersonate ?? []);
        if (!(bool)($impersonate['active'] ?? false)) {
            return;
        }

        $actor = (string)($impersonate['actor'] ?? '');
        $target = (string)($impersonate['target'] ?? '');
        if ($actor !== '') {
            $this->impersonator->activeStore()->clear($actor);
            $this->impersonator->logEvent('impersonate_stop', $actor, $target, 'ok', $reason);
        }

        unset($session->impersonate);
    }

    private function handleStartRoute(string $token): void
    {
        $payload = $this->impersonator->consumeToken($token, 'start');
        if (!$payload) {
            $this->impersonator->logEvent('impersonate_fail', (string)($this->grav['user']->username ?? ''), '', 'failed', 'start_token_invalid');
            if ($this->wantsJsonResponse()) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Invalid token'], 400);
            }
            $this->grav->redirect('/');
            return;
        }

        $actor = (string)$payload['actor_user'];
        $target = (string)$payload['target_user'];
        $mode = (string)($payload['mode'] ?? 'user');
        $allowAdminTargets = (bool)$this->grav['config']->get('plugins.impersonate.allow_admin_targets', false);

        /** @var \Grav\Common\User\Interfaces\UserCollectionInterface $accounts */
        $accounts = $this->grav['accounts'];
        $user = $accounts->load($target);
        if (
            !$user
            || !$user->exists()
            || ($user->state ?? 'enabled') !== 'enabled'
            || !$this->impersonator->isSiteLoginEnabled($user)
        ) {
            $this->impersonator->logEvent('impersonate_fail', $actor, $target, 'failed', 'target_missing_or_disabled');
            if ($this->wantsJsonResponse()) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Target user unavailable'], 404);
            }
            $this->grav->redirect('/');
            return;
        }

        // Defense-in-depth: re-check admin target restriction during frontend activation.
        // Do not rely solely on admin task checks, which could regress across updates.
        $targetIsAdmin = $this->impersonator->isAdminAccount($user);
        $guard = new AdminTargetGuard();
        if (!$guard->canActivate($mode, $allowAdminTargets, $targetIsAdmin)) {
            $this->impersonator->logEvent('impersonate_fail', $actor, $target, 'denied', 'target_is_admin');
            if ($this->wantsJsonResponse()) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Target cannot be impersonated'], 403);
            }
            $this->grav->redirect('/');
            return;
        }

        $session = $this->grav['session'];
        if (method_exists($session, 'regenerateId')) {
            $session->regenerateId();
        }

        $session->user = $user;
        $session->impersonate = [
            'active' => true,
            'actor' => $actor,
            'target' => $target,
            'mode' => $mode,
            'started_at' => time(),
        ];

        unset($this->grav['user']);
        $this->grav['user'] = $user;
        $user->authenticated = true;
        $user->authorized = true;

        $existing = $this->impersonator->activeStore()->get($actor);
        $eventName = ($mode === 'self') ? 'impersonate_self_start' : ($existing && ($existing['target'] ?? '') !== $target ? 'impersonate_switch' : 'impersonate_start');
        $this->impersonator->activeStore()->set($actor, [
            'target' => $target,
            'mode' => $mode,
            'updated_at' => time(),
        ]);
        $this->impersonator->logEvent($eventName, $actor, $target, 'ok', 'token_consumed');

        $redirect = $this->impersonator->safeRedirect((string)($payload['redirect'] ?? '/'));
        if ($this->wantsJsonResponse()) {
            $this->jsonResponse(['status' => 'success', 'redirect' => $redirect]);
        }

        $this->grav->redirect($redirect);
    }

    private function handleStopRoute(): void
    {
        $validator = new FrontendStopRequestValidator();
        if (!$validator->isMethodAllowed((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'))) {
            if ($this->wantsJsonResponse()) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
            }
            $this->grav->redirect('/');
            return;
        }

        $post = (array)$this->grav['uri']->post();
        $nonce = (string)($post['impersonate-stop-nonce'] ?? $this->grav['uri']->param('impersonate-stop-nonce') ?? '');
        if (!$validator->isNonceValid($nonce, static function (string $value, string $action): bool {
            return Utils::verifyNonce($value, $action);
        })) {
            $this->impersonator->logEvent('impersonate_fail', (string)($this->grav['user']->username ?? ''), '', 'denied', 'stop_nonce_invalid');
            if ($this->wantsJsonResponse()) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Invalid security token'], 403);
            }
            $this->grav->redirect('/');
            return;
        }

        $tokenResolver = new FrontendStopTokenResolver();
        $token = $tokenResolver->resolveFromPost($post);
        if (!$token) {
            $this->impersonator->logEvent('impersonate_fail', (string)($this->grav['user']->username ?? ''), '', 'failed', 'stop_token_missing');
            if ($this->wantsJsonResponse()) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Missing token'], 422);
            }
            $this->grav->redirect('/');
            return;
        }

        $payload = $this->impersonator->consumeToken($token, 'stop');
        if (!$payload) {
            $this->impersonator->logEvent('impersonate_fail', (string)($this->grav['user']->username ?? ''), '', 'failed', 'stop_token_invalid');
            if ($this->wantsJsonResponse()) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Invalid token'], 400);
            }
            $this->grav->redirect('/');
            return;
        }

        $actor = (string)$payload['actor_user'];
        $session = $this->grav['session'];
        if (method_exists($session, 'regenerateId')) {
            $session->regenerateId();
        }

        /** @var \Grav\Common\User\Interfaces\UserCollectionInterface $accounts */
        $accounts = $this->grav['accounts'];
        $guest = $accounts->load('');
        if ($guest) {
            $guest->authenticated = false;
            $guest->authorized = false;
            $session->user = $guest;
            unset($this->grav['user']);
            $this->grav['user'] = $guest;
        }

        unset($session->impersonate);
        $this->impersonator->activeStore()->clear($actor);
        $this->impersonator->logEvent('impersonate_stop', $actor, '', 'ok', 'token_consumed');

        $redirect = $this->impersonator->safeRedirect((string)($payload['redirect'] ?? '/'));
        if ($this->wantsJsonResponse()) {
            $this->jsonResponse(['status' => 'success', 'redirect' => $redirect]);
        }

        $this->grav->redirect($redirect);
    }

    private function handleStatusRoute(): void
    {
        $state = $this->getFrontendImpersonateState();
        $active = (bool)($state['active'] ?? false);
        $actor = (string)($state['actor'] ?? '');
        $target = (string)($state['target'] ?? '');
        $mode = (string)($state['mode'] ?? '');
        $requestedActor = trim((string)($this->grav['uri']->query('actor') ?? ''));

        if ($requestedActor !== '' && (!$active || $actor !== $requestedActor)) {
            $this->impersonator->activeStore()->clear($requestedActor);
            $this->jsonResponse([
                'status' => 'success',
                'state' => [
                    'active' => false,
                    'actor' => $requestedActor,
                    'target' => '',
                    'mode' => '',
                    'stop_nonce' => '',
                ]
            ]);
        }

        $this->jsonResponse([
            'status' => 'success',
                'state' => [
                    'active' => $active,
                    'actor' => $actor,
                    'target' => $target,
                    'mode' => $mode,
                    'stop_nonce' => $active ? Utils::getNonce('impersonate-stop') : '',
                ]
            ]);
    }

    private function getFrontendImpersonateState(): array
    {
        $session = $this->grav['session'];
        $impersonate = (array)($session->impersonate ?? []);
        $state = [
            'active' => (bool)($impersonate['active'] ?? false),
            'actor' => (string)($impersonate['actor'] ?? ''),
            'target' => (string)($impersonate['target'] ?? ''),
            'mode' => (string)($impersonate['mode'] ?? ''),
        ];

        if (!$state['active']) {
            return $state;
        }

        if ($this->isFrontendImpersonateStateValid($state)) {
            return $state;
        }

        $this->clearStaleFrontendImpersonateState($state, 'frontend_state_stale');

        return [
            'active' => false,
            'actor' => $state['actor'],
            'target' => '',
            'mode' => '',
        ];
    }

    private function isFrontendImpersonateStateValid(array $state): bool
    {
        $actor = (string)($state['actor'] ?? '');
        $target = (string)($state['target'] ?? '');
        if ($actor === '' || $target === '') {
            return false;
        }

        $user = $this->grav['user'] ?? null;
        if (!is_object($user)) {
            return false;
        }

        $username = (string)($user->username ?? '');
        $authenticated = (bool)($user->authenticated ?? false);

        return $authenticated && $username !== '' && $username === $target;
    }

    private function clearStaleFrontendImpersonateState(array $state, string $reason): void
    {
        $session = $this->grav['session'];
        $actor = (string)($state['actor'] ?? '');
        $target = (string)($state['target'] ?? '');

        if ($actor !== '') {
            $this->impersonator->activeStore()->clear($actor);
            $this->impersonator->logEvent('impersonate_stop', $actor, $target, 'ok', $reason);
        }

        unset($session->impersonate);
    }

    private function wantsJsonResponse(): bool
    {
        if ($this->isAjaxRequest()) {
            return true;
        }

        $format = (string)($this->grav['uri']->param('format') ?? $this->grav['uri']->query('format') ?? '');

        return strtolower($format) === 'json';
    }

    private function isAjaxRequest(): bool
    {
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        $format = (string)($this->grav['uri']->query('format') ?? '');

        return $format === 'json';
    }

    private function jsonResponse(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
