<?php

declare(strict_types=1);

namespace Grav\Plugin\Impersonate\Admin;

final class PluginRouteMatcher
{
    public function shouldLoad(string $pluginName, string $uriRoute, ?string $adminRoute = null, string $adminBase = 'admin'): bool
    {
        $pluginRoute = 'plugins/' . trim($pluginName, '/');

        $adminRoute = trim((string)$adminRoute, '/');
        if ($adminRoute !== '' && ($adminRoute === $pluginRoute || strpos($adminRoute, $pluginRoute . '/') === 0)) {
            return true;
        }

        $uriRoute = trim($uriRoute, '/');
        if ($uriRoute === $pluginRoute || strpos($uriRoute, $pluginRoute . '/') === 0) {
            return true;
        }

        $adminBase = trim($adminBase, '/');
        if ($adminBase === '') {
            return false;
        }

        $fullRoute = $adminBase . '/' . $pluginRoute;

        return $uriRoute === $fullRoute || strpos($uriRoute, $fullRoute . '/') === 0;
    }
}
