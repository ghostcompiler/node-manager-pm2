<?php
class Modules_NodeManagerPm2_ConfigDefaults extends pm_Hook_ConfigDefaults
{
    public function getDefaults()
    {
        return [
            'pm2Binary' => 'pm2',
            'nodeBinary' => 'node',
            'npmBinary' => 'npm',
            'gitBinary' => 'git',
            'extraPath' => '/usr/local/bin:/opt/plesk/node/bin',
            'pollInterval' => '5000',
            'maxLogBytes' => '200000',
            'metricsRetentionDays' => '14',
            'deploymentTimeout' => '900',
        ];
    }
}
