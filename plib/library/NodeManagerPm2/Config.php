<?php
class Modules_NodeManagerPm2_Config
{
    public static function defaults()
    {
        return [
            'pm2Binary' => 'pm2',
            'nodeBinary' => 'node',
            'npmBinary' => 'npm',
            'gitBinary' => 'git',
            'extraPath' => '/usr/local/bin:/usr/bin:/bin:/opt/plesk/node/bin',
            'pollInterval' => '5000',
            'maxLogBytes' => '200000',
            'metricsRetentionDays' => '14',
            'deploymentTimeout' => '900',
        ];
    }

    public static function ensureDefaults()
    {
        foreach (self::defaults() as $key => $value) {
            if (self::get($key, null) === null) {
                self::set($key, $value);
            }
        }
    }

    public static function all()
    {
        $values = [];
        foreach (self::defaults() as $key => $value) {
            $values[$key] = self::get($key, $value);
        }
        return $values;
    }

    public static function get($key, $default = null)
    {
        if (class_exists('pm_Settings')) {
            $value = pm_Settings::get($key, null);
            return $value === null ? $default : $value;
        }

        return Modules_NodeManagerPm2_Store::instance()->getSetting($key, $default);
    }

    public static function set($key, $value)
    {
        if (!array_key_exists($key, self::defaults())) {
            throw new Modules_NodeManagerPm2_Exception('Unknown setting: ' . $key);
        }

        if (class_exists('pm_Settings')) {
            pm_Settings::set($key, (string) $value);
            return;
        }

        Modules_NodeManagerPm2_Store::instance()->setSetting($key, (string) $value);
    }

    public static function varDir()
    {
        if (class_exists('pm_Context') && (!method_exists('pm_Context', 'isInitialized') || pm_Context::isInitialized())) {
            return pm_Context::getVarDir();
        }

        return dirname(__DIR__, 3) . '/var';
    }

    public static function htdocsDir()
    {
        if (class_exists('pm_Context') && (!method_exists('pm_Context', 'isInitialized') || pm_Context::isInitialized())) {
            return pm_Context::getHtdocsDir();
        }

        return dirname(__DIR__, 3) . '/htdocs';
    }
}
