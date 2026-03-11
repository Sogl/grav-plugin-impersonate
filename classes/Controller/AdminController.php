<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Controller;

use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Plugin\Impersonate\Admin\LogTailReader;
use Grav\Plugin\Impersonate\Admin\PluginRouteMatcher;
use Grav\Plugin\Impersonate\Security\ImpersonationPolicy;
use Grav\Plugin\Impersonate\Service\Impersonator;
use RocketTheme\Toolbox\Event\Event;

class AdminController
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

    public function onAssetsInitialized(): void
    {
        if (!$this->impersonator->isSplitEnabled() || !(bool)$this->grav['config']->get('plugins.impersonate.show_ui_button', true)) {
            return;
        }

        $actor = (string)($this->grav['user']->username ?? '');
        $activeState = $actor !== '' ? $this->impersonator->activeStore()->get($actor) : null;
        $state = [
            'active' => is_array($activeState),
            'target' => is_array($activeState) ? (string)($activeState['target'] ?? '') : '',
            'mode' => is_array($activeState) ? (string)($activeState['mode'] ?? '') : '',
            'actor' => $actor,
        ];
        $adminConfig = [
            'confirmOnSwitch' => (bool)$this->grav['config']->get('plugins.impersonate.confirm_on_switch', true),
            'statusUrl' => $this->impersonator->frontendUrl('/impersonate/status'),
            'icons' => [
                'start' => $this->impersonator->iconClass('icon_start', 'fa-arrow-right-arrow-left'),
                'stop' => $this->impersonator->iconClass('icon_stop', 'fa-arrow-right-from-bracket'),
                'self' => $this->impersonator->iconClass('icon_self', 'fa-arrow-right-to-bracket'),
            ],
            'texts' => [
                'switchTitle' => (string)$this->grav['language']->translate('PLUGIN_IMPERSONATE.MODAL.SWITCH_TITLE'),
                'switchCancel' => (string)$this->grav['language']->translate('PLUGIN_IMPERSONATE.MODAL.SWITCH_CANCEL'),
                'switchConfirm' => (string)$this->grav['language']->translate('PLUGIN_IMPERSONATE.MODAL.SWITCH_CONFIRM'),
                'switchText' => (string)$this->grav['language']->translate('PLUGIN_IMPERSONATE.MODAL.SWITCH_TEXT'),
            ],
        ];
        $stateJson = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $configJson = json_encode($adminConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($stateJson !== false) {
            $this->grav['assets']->addInlineJs(
                'window.GravImpersonateAdminState = ' . $stateJson . ';',
                ['group' => 'bottom', 'priority' => 98]
            );
        }
        if ($configJson !== false) {
            $this->grav['assets']->addInlineJs(
                'window.GravImpersonateAdminConfig = ' . $configJson . ';',
                ['group' => 'bottom', 'priority' => 98]
            );
        }

        $this->grav['assets']->addJs('plugin://impersonate/assets/admin/impersonate-tray.js', [
            'group' => 'bottom',
            'loading' => 'defer',
            'priority' => 100,
        ]);

        $this->grav['assets']->addJs('plugin://impersonate/assets/admin/impersonate-logs.js', [
            'group' => 'bottom',
            'loading' => 'defer',
            'priority' => 99,
        ]);
    }

    public function onAdminMenu(): void
    {
        if (!$this->impersonator->isSplitEnabled() || !(bool)$this->grav['config']->get('plugins.impersonate.show_ui_button', true)) {
            return;
        }

        $actor = (string)($this->grav['user']->username ?? '');
        if ($actor === '') {
            return;
        }

        $activeState = $this->impersonator->activeStore()->get($actor);
        $activeMode = is_array($activeState) ? (string)($activeState['mode'] ?? '') : '';
        if ($activeState && $activeMode === 'self') {
            $stopRoute = $this->adminTaskRoute('stopImpersonate');

            $this->grav['twig']->plugins_quick_tray['Stop Impersonate'] = [
                // Stop доступен тем, кто может impersonate (self/users) или super.
                'authorize' => ['admin.impersonate.self', 'admin.impersonate.users', 'admin.super'],
                'hint' => 'Stop frontend impersonation',
                'class' => 'impersonate-stop',
                'icon' => $this->impersonator->iconClass('icon_stop', 'fa-arrow-right-from-bracket'),
                'route' => $stopRoute,
                'target' => '_blank'
            ];

            return;
        }

        $selfRoute = $this->adminTaskRoute('impersonateSelf');

        $this->grav['twig']->plugins_quick_tray['Impersonate Self'] = [
            'authorize' => ['admin.impersonate.self', 'admin.super'],
            'hint' => 'Open frontend as current admin',
            'class' => 'impersonate-self',
            'icon' => $this->impersonator->iconClass('icon_self', 'fa-arrow-right-to-bracket'),
            'route' => $selfRoute,
            'target' => '_blank'
        ];
    }

    public function onAdminTwigTemplatePaths(Event $event): void
    {
        $paths = (array)($event['paths'] ?? []);
        // Assuming this file is in classes/Controller, we need to go up 2 levels then admin/templates
        $paths[] = dirname(__DIR__, 2) . '/admin/templates';
        $event['paths'] = $paths;
    }

    public function populateAdminTwigVars(): void
    {
        if (!$this->impersonator->isSplitEnabled()) {
            $this->grav['twig']->twig_vars['impersonate_state'] = [
                'active' => false,
                'target' => '',
                'mode' => '',
                'actor' => '',
            ];
            if ($this->shouldLoadLogViewerContext()) {
                $this->grav['twig']->twig_vars['impersonate_log_fetch_url'] = $this->adminTaskRoute('getImpersonateLog');
                $this->grav['twig']->twig_vars['impersonate_log_clear_url'] = $this->adminTaskRoute('clearImpersonateLog');
                $this->grav['twig']->twig_vars['impersonate_log_path'] = 'logs/impersonate.log';
            }
            return;
        }

        $actor = (string)($this->grav['user']->username ?? '');
        $activeState = $actor !== '' ? $this->impersonator->activeStore()->get($actor) : null;
        $this->grav['twig']->twig_vars['impersonate_state'] = [
            'active' => is_array($activeState),
            'target' => is_array($activeState) ? (string)($activeState['target'] ?? '') : '',
            'mode' => is_array($activeState) ? (string)($activeState['mode'] ?? '') : '',
            'actor' => $actor,
        ];
        if ($this->shouldLoadLogViewerContext()) {
            $this->grav['twig']->twig_vars['impersonate_log_fetch_url'] = $this->adminTaskRoute('getImpersonateLog');
            $this->grav['twig']->twig_vars['impersonate_log_clear_url'] = $this->adminTaskRoute('clearImpersonateLog');
            $this->grav['twig']->twig_vars['impersonate_log_path'] = 'logs/impersonate.log';
        }
    }

    public function onAdminTaskExecute(Event $event): void
    {
        $method = strtolower((string)($event['method'] ?? ''));
        if (!in_array($method, ['taskimpersonate', 'taskimpersonateself', 'taskstopimpersonate', 'taskclearimpersonatelog', 'taskgetimpersonatelog'], true)) {
            return;
        }

        $actorUser = $this->grav['user'];
        $actor = (string)($actorUser->username ?? '');
        $isSuper = (bool)$actorUser->authorize('admin.super');
        $policy = new ImpersonationPolicy();

        // Important: do not rely on Grav Admin internals for nonce validation — verify it here.
        // Otherwise changes in hook order / admin flow can re-introduce CSRF for task endpoints.
        $adminNonce = $this->adminTaskNonce();
        if (trim($adminNonce) === '' || !Utils::verifyNonce($adminNonce, 'admin-form')) {
            $this->impersonator->logEvent('impersonate_fail', $actor, '', 'denied', 'admin_nonce_invalid');
            $this->jsonResponse(['status' => 'error', 'message' => 'Invalid security token'], 403);
        }

        if ($method === 'taskclearimpersonatelog') {
            // Logs use a dedicated permission (not tied to impersonate stop).
            if (!$isSuper && !(bool)$actorUser->authorize('admin.impersonate.logs')) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions'], 403);
            }

            $this->clearLogFile();
            $this->impersonator->logEvent('impersonate_log_cleared', $actor, '', 'ok', 'manual_admin_clear');

            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['status' => 'success']);
            }

            $this->grav->redirect($this->pluginConfigRoute());
            return;
        }

        if ($method === 'taskgetimpersonatelog') {
            // Logs use a dedicated permission (not tied to impersonate stop).
            if (!$isSuper && !(bool)$actorUser->authorize('admin.impersonate.logs')) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions'], 403);
            }

            $this->jsonResponse([
                'status' => 'success',
                'content' => $this->readLogTail(),
            ]);
        }

        if (!$this->impersonator->isSplitEnabled()) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Session split must be enabled'], 409);
        }

        if ($method === 'taskimpersonateself') {
            $allowed = $policy->canImpersonateSelf($this->impersonator->isSplitEnabled(), (bool)$actorUser->authorize('admin.impersonate.self'), $isSuper);
            if (!$allowed) {
                $this->impersonator->logEvent('impersonate_fail', $actor, $actor, 'denied', 'permission_self');
                $this->jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions'], 403);
            }

            $url = $this->impersonator->issueStartUrl($actor, 'self');
            $this->respondWithUrl($url);
        }

        if ($method === 'taskstopimpersonate') {
            // Treat stop as part of impersonate capability: if you can start (self/users) or are super, you can stop.
            $canStop = (bool)$actorUser->authorize('admin.impersonate.self')
                || (bool)$actorUser->authorize('admin.impersonate.users')
                || $isSuper;
            $allowed = $policy->canStop($this->impersonator->isSplitEnabled(), $canStop, $isSuper);
            if (!$allowed) {
                $this->impersonator->logEvent('impersonate_fail', $actor, '', 'denied', 'permission_stop');
                $this->jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions'], 403);
            }

            $url = $this->impersonator->issueStopUrl($actor);
            $this->respondWithUrl($url);
        }

        $target = $this->impersonator->resolveTargetUsername();
        if (!$target) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Missing target user'], 422);
        }

        /** @var \Grav\Common\User\Interfaces\UserCollectionInterface $accounts */
        $accounts = $this->grav['accounts'];
        $targetUser = $accounts->load($target);
        if (!$targetUser || !$targetUser->exists()) {
            $this->impersonator->logEvent('impersonate_fail', $actor, $target, 'failed', 'target_not_found');
            $this->jsonResponse(['status' => 'error', 'message' => 'Target user not found'], 404);
        }

        $targetIsAdmin = (bool)$targetUser->authorize('admin.login') || (bool)$targetUser->authorize('admin.super');
        $targetEnabled = ($targetUser->state ?? 'enabled') === 'enabled';
        $targetLoginEnabled = $this->impersonator->isSiteLoginEnabled($targetUser);
        $allowed = $policy->canImpersonateUser(
            $this->impersonator->isSplitEnabled(),
            (bool)$actorUser->authorize('admin.impersonate.users'),
            $isSuper,
            $targetIsAdmin && !(bool)$this->grav['config']->get('plugins.impersonate.allow_admin_targets', false),
            $targetEnabled,
            $targetLoginEnabled,
            false // allowLoginDisabledTargets is now always false
        );

        // Fix: temporarily set authenticated to ensure authorize() works for target user
        $originalAuth = $targetUser->authenticated;
        $targetUser->authenticated = true;
        $realTargetIsAdmin = (bool)$targetUser->authorize('admin.login') || (bool)$targetUser->authorize('admin.super');
        $targetUser->authenticated = $originalAuth;

        if ($realTargetIsAdmin && !(bool)$this->grav['config']->get('plugins.impersonate.allow_admin_targets', false)) {
            $allowed = false;
        }
        if (!$allowed) {
            $reason = !$targetLoginEnabled
                ? 'target_site_login_disabled'
                : 'permission_or_target_restriction';
            $this->impersonator->logEvent('impersonate_fail', $actor, $target, 'denied', $reason);
            $this->jsonResponse(['status' => 'error', 'message' => 'Target cannot be impersonated'], 403);
        }

        $url = $this->impersonator->issueStartUrl($target, 'user');
        $this->respondWithUrl($url);
    }

    private function adminTaskRoute(string $task): string
    {
        $paramSep = (string)$this->grav['config']->get('system.param_sep', ':');
        $adminRoute = (string)$this->grav['config']->get('plugins.admin.route', '/admin');
        $adminRoute = '/' . trim($adminRoute, '/');
        $route = $adminRoute . '/task' . $paramSep . $task;

        return $this->grav['uri']->addNonce($route, 'admin-form', 'admin-nonce');
    }

    private function shouldLoadLogViewerContext(): bool
    {
        $adminRoute = null;
        $admin = $this->grav['admin'] ?? null;
        if ($admin && isset($admin->route)) {
            $adminRoute = (string)$admin->route;
        }
        $route = trim((string)$this->grav['uri']->route(), '/');
        $adminBase = trim((string)$this->grav['config']->get('plugins.admin.route', 'admin'), '/');
        $matcher = new PluginRouteMatcher();

        return $matcher->shouldLoad('impersonate', $route, $adminRoute, $adminBase);
    }

    private function readLogTail(int $maxBytes = 200000): string
    {
        $file = $this->grav['locator']->findResource('log://impersonate.log', true, true);
        if (!$file) {
            return '';
        }

        $reader = new LogTailReader();

        return $reader->read($file, $maxBytes);
    }

    private function clearLogFile(): void
    {
        $file = $this->grav['locator']->findResource('log://impersonate.log', true, true);
        if (!$file) {
            return;
        }

        file_put_contents($file, '');
    }

    private function pluginConfigRoute(): string
    {
        $adminRoute = (string)$this->grav['config']->get('plugins.admin.route', '/admin');
        $adminRoute = '/' . trim($adminRoute, '/');

        return $adminRoute . '/plugins/impersonate';
    }

    private function adminTaskNonce(): string
    {
        $param = (string)($this->grav['uri']->param('admin-nonce') ?? '');
        if (trim($param) !== '') {
            return $param;
        }

        $post = (array)$this->grav['uri']->post();
        return (string)($post['admin-nonce'] ?? '');
    }

    private function jsonResponse(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function respondWithUrl(string $url): void
    {
        if ($this->isAjaxRequest()) {
            $this->jsonResponse(['status' => 'success', 'url' => $url]);
        }

        $this->grav->redirect($url);
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
}
