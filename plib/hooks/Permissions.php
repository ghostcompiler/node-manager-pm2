<?php
require_once pm_Context::getPlibDir() . '/library/NodeManagerPm2/Autoload.php';
Modules_NodeManagerPm2_Autoload::register();

class Modules_NodeManagerPm2_Permissions extends pm_Hook_Permissions
{
    public function getPermissions()
    {
        return [
            Modules_NodeManagerPm2_PermissionService::ACCESS => [
                'default' => false,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'Node Manager (PM2) access',
                'description' => 'Allow access to assigned PM2 applications in Plesk.',
            ],
            Modules_NodeManagerPm2_PermissionService::CONTROL => [
                'default' => false,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'Control PM2 processes',
                'description' => 'Allow start, stop, restart, reload, and scaling actions for assigned PM2 applications.',
                'master' => Modules_NodeManagerPm2_PermissionService::ACCESS,
            ],
            Modules_NodeManagerPm2_PermissionService::LOGS => [
                'default' => false,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'View PM2 logs',
                'description' => 'Allow viewing and downloading PM2 logs for assigned applications.',
                'master' => Modules_NodeManagerPm2_PermissionService::ACCESS,
            ],
            Modules_NodeManagerPm2_PermissionService::MANAGE => [
                'default' => false,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'Manage PM2 applications',
                'description' => 'Allow creating, deleting, deploying, and editing environment or ecosystem settings for assigned domains.',
                'master' => Modules_NodeManagerPm2_PermissionService::ACCESS,
            ],
        ];
    }
}
