<?php
require_once pm_Context::getPlibDir() . '/library/NodeManagerPm2/Autoload.php';
Modules_NodeManagerPm2_Autoload::register();

class Modules_NodeManagerPm2_Limits extends pm_Hook_Limits
{
    public function getLimits()
    {
        return [
            Modules_NodeManagerPm2_PermissionService::LIMIT_APPS => [
                'default' => 0,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'Maximum PM2 applications',
                'description' => 'Maximum number of PM2 applications that can be created for a subscription. Use -1 for unlimited.',
            ],
        ];
    }
}
