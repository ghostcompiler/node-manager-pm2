<?php
require_once pm_Context::getPlibDir() . '/library/NodeManagerPm2/Autoload.php';
Modules_NodeManagerPm2_Autoload::register();

class Modules_NodeManagerPm2_Navigation extends pm_Hook_Navigation
{
    public function getNavigation()
    {
        if (
            !Modules_NodeManagerPm2_PermissionService::isAdmin() &&
            !Modules_NodeManagerPm2_PermissionService::canAny(Modules_NodeManagerPm2_PermissionService::ACCESS)
        ) {
            return [];
        }

        return [
            [
                'controller' => 'index',
                'action' => 'index',
                'label' => 'Node Manager (PM2)',
                'pages' => [
                    [
                        'controller' => 'index',
                        'action' => 'domain',
                        'label' => 'Domain Processes',
                    ],
                    [
                        'controller' => 'index',
                        'action' => 'info',
                        'label' => 'Info',
                    ],
                ],
            ],
        ];
    }
}
