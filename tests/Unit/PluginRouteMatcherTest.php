<?php

declare(strict_types=1);

use Grav\Plugin\Impersonate\Admin\PluginRouteMatcher;
use PHPUnit\Framework\TestCase;

final class PluginRouteMatcherTest extends TestCase
{
    public function testMatchesAdminRouteFromAdminObject(): void
    {
        $matcher = new PluginRouteMatcher();

        $this->assertTrue($matcher->shouldLoad('impersonate', 'admin/dashboard', 'plugins/impersonate', 'admin'));
    }

    public function testMatchesPlainPluginRoute(): void
    {
        $matcher = new PluginRouteMatcher();

        $this->assertTrue($matcher->shouldLoad('impersonate', 'plugins/impersonate', null, 'admin'));
    }

    public function testMatchesAdminBasePrefixedRoute(): void
    {
        $matcher = new PluginRouteMatcher();

        $this->assertTrue($matcher->shouldLoad('impersonate', 'admin/plugins/impersonate', null, 'admin'));
    }

    public function testDoesNotMatchUnrelatedRoute(): void
    {
        $matcher = new PluginRouteMatcher();

        $this->assertFalse($matcher->shouldLoad('impersonate', 'admin/pages', null, 'admin'));
    }
}
