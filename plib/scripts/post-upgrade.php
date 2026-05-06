<?php
require_once dirname(__DIR__) . '/library/NodeManagerPm2/Autoload.php';
Modules_NodeManagerPm2_Autoload::register();

try {
    Modules_NodeManagerPm2_Store::instance()->initialize();
    Modules_NodeManagerPm2_Config::ensureDefaults();
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

exit(0);
