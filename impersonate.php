<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Events\PermissionsRegisterEvent;
use Grav\Framework\Acl\PermissionsReader;
use Composer\Autoload\ClassLoader;
use Grav\Plugin\Impersonate\Controller\AdminController;
use Grav\Plugin\Impersonate\Controller\FrontendController;
use Grav\Plugin\Impersonate\Service\Impersonator;
use RocketTheme\Toolbox\Event\Event;

class ImpersonatePlugin extends Plugin
{
    /** @var Impersonator */
    protected $impersonator;

    /** @var AdminController */
    protected $adminController;

    /** @var FrontendController */
    protected $frontendController;

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0]
            ],
            PermissionsRegisterEvent::class => ['onRegisterPermissions', 100]
        ];
    }

    /**
     * Composer autoload.
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }



    public function onPluginsInitialized(): void
    {
        // Init Service
        $this->impersonator = new Impersonator($this->grav);

        // Init Controllers
        $this->adminController = new AdminController($this->grav, $this->impersonator);
        $this->frontendController = new FrontendController($this->grav, $this->impersonator);

        if ($this->isAdmin()) {
            $this->enable([
                'onAdminTaskExecute' => ['onAdminTaskExecute', 0],
                'onAdminMenu' => ['onAdminMenu', 0],
                'onAdminTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 0],
                'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
                'onAssetsInitialized' => ['onAssetsInitialized', 0],
                'onTwigInitialized' => ['onTwigInitialized', 0],
            ]);

            return;
        }

        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onUserLogout' => ['onUserLogout', 0],
            'onTask.login.logout' => ['onFrontendLogoutTask', 0],
            'onAssetsInitialized' => ['onAssetsInitialized', 0],
        ]);
    }

    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig()->addFunction(new \Twig\TwigFunction('impersonate_is_admin', function ($user) {
            return $this->impersonator->isAdminAccount($user);
        }));
    }

    public function onRegisterPermissions(PermissionsRegisterEvent $event): void
    {
        $actions = PermissionsReader::fromYaml("plugin://{$this->name}/permissions.yaml");
        $event->permissions->addActions($actions);
    }

    public function onAdminMenu(): void
    {
        $this->adminController->onAdminMenu();
    }

    public function onAdminTwigTemplatePaths(Event $event): void
    {
        $this->adminController->onAdminTwigTemplatePaths($event);
    }

    public function onTwigSiteVariables(): void
    {
        if (!$this->isAdmin()) {
            return;
        }
        $this->adminController->populateAdminTwigVars();
    }

    public function onAdminTaskExecute(Event $event): void
    {
        $this->adminController->onAdminTaskExecute($event);
    }

    public function onPageInitialized(): void
    {
        $this->frontendController->onPageInitialized();
    }

    public function onAssetsInitialized(): void
    {
        if ($this->isAdmin()) {
            $this->adminController->onAssetsInitialized();
        } else {
            $this->frontendController->onAssetsInitialized();
        }
    }

    public function onUserLogout(Event $event): void
    {
        $this->frontendController->onUserLogout($event);
    }

    public function onFrontendLogoutTask(Event $event): void
    {
        $this->frontendController->onFrontendLogoutTask($event);
    }


    

}
