<?php
class Modules_NodeManagerPm2_Autoload
{
    public static function register()
    {
        spl_autoload_register([__CLASS__, 'load']);
    }

    public static function load($class)
    {
        $prefix = 'Modules_NodeManagerPm2_';
        if (strpos($class, $prefix) !== 0) {
            return false;
        }

        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/' . str_replace('_', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
            return true;
        }

        return false;
    }
}
