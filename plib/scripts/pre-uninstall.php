<?php
require_once dirname(__DIR__) . '/library/NodeManagerPm2/Autoload.php';
Modules_NodeManagerPm2_Autoload::register();

try {
    Modules_NodeManagerPm2_Store::instance()->close();
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
}

exit(0);
