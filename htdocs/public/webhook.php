<?php
require_once '/usr/local/psa/admin/plib/api-common/pm/Context.php';

if (!class_exists('pm_Context') || !pm_Context::isInitialized()) {
    pm_Context::init('node-manager-pm2');
}

require_once pm_Context::getPlibDir() . '/library/NodeManagerPm2/Autoload.php';
Modules_NodeManagerPm2_Autoload::register();
Modules_NodeManagerPm2_PublicWebhook::handle();
