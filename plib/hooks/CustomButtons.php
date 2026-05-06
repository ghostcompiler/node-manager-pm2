<?php
require_once pm_Context::getPlibDir() . '/library/NodeManagerPm2/Autoload.php';
Modules_NodeManagerPm2_Autoload::register();

class Modules_NodeManagerPm2_CustomButtons extends pm_Hook_CustomButtons
{
    public function getButtons()
    {
        $icon = pm_Context::getBaseUrl() . 'images/icon.png';
        $link = pm_Context::getActionUrl('index', 'index');
        $domainLink = pm_Context::getActionUrl('index', 'domain');
        $isAdmin = Modules_NodeManagerPm2_PermissionService::isAdmin();
        $hasAccess = $isAdmin || Modules_NodeManagerPm2_PermissionService::canAny(Modules_NodeManagerPm2_PermissionService::ACCESS);

        $buttons = [];
        if ($isAdmin) {
            $buttons[] = [
                'place' => self::PLACE_ADMIN_NAVIGATION,
                'section' => self::SECTION_NAV_SERVER_MANAGEMENT,
                'title' => 'Node Manager (PM2)',
                'description' => 'Manage Node.js processes with PM2',
                'icon' => $icon,
                'link' => $link,
                'newWindow' => false,
            ];
        }

        if (!$hasAccess) {
            return $buttons;
        }

        if (!$isAdmin) {
            $buttons[] = [
                'place' => self::PLACE_RESELLER_NAVIGATION,
                'section' => self::SECTION_NAV_ADDITIONAL,
                'title' => 'Node Manager (PM2)',
                'description' => 'Manage domain Node.js PM2 apps',
                'icon' => $icon,
                'link' => $link,
                'newWindow' => false,
            ];
        }

        $buttons[] = [
            'place' => self::PLACE_CUSTOMER_HOME,
            'title' => 'Node Manager (PM2)',
            'description' => 'Manage domain Node.js PM2 apps',
            'icon' => $icon,
            'link' => $link,
            'newWindow' => false,
        ];

        $buttons[] = [
            'place' => self::PLACE_DOMAIN,
            'title' => 'Node Manager (PM2)',
            'description' => 'Manage domain Node.js PM2 apps',
            'icon' => $icon,
            'link' => $domainLink,
            'newWindow' => false,
            'contextParams' => true,
        ];

        if (
            defined('pm_Hook_CustomButtons::PLACE_DOMAIN_PROPERTIES_DYNAMIC') &&
            defined('pm_Hook_CustomButtons::SECTION_DOMAIN_PROPS_DYNAMIC_DEV_TOOLS')
        ) {
            $buttons[] = [
                'place' => constant('pm_Hook_CustomButtons::PLACE_DOMAIN_PROPERTIES_DYNAMIC'),
                'section' => constant('pm_Hook_CustomButtons::SECTION_DOMAIN_PROPS_DYNAMIC_DEV_TOOLS'),
                'title' => 'Node Manager (PM2)',
                'description' => 'Manage Node.js processes with PM2',
                'icon' => $icon,
                'link' => $domainLink,
                'newWindow' => false,
                'contextParams' => true,
            ];
        }

        return $buttons;
    }
}
