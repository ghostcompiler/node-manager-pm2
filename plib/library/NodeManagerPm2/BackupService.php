<?php
class Modules_NodeManagerPm2_BackupService
{
    public function create()
    {
        $data = Modules_NodeManagerPm2_Store::instance()->exportData();
        $data['ecosystem_configs'] = [];
        foreach ($data['apps'] as $app) {
            if (!empty($app['ecosystem_path']) && is_file($app['ecosystem_path'])) {
                $data['ecosystem_configs'][] = [
                    'app_id' => $app['id'],
                    'path' => $app['ecosystem_path'],
                    'content' => file_get_contents($app['ecosystem_path']),
                ];
            }
        }
        $file = Modules_NodeManagerPm2_Config::varDir() . '/backups/node-manager-pm2-' . gmdate('Ymd-His') . '.json';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($file, 0640);
        return ['file' => $file, 'data' => $data];
    }

    public function listBackups()
    {
        $dir = Modules_NodeManagerPm2_Config::varDir() . '/backups';
        if (!is_dir($dir)) {
            return [];
        }

        $items = [];
        foreach (glob($dir . '/*.json') as $file) {
            $items[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'createdAt' => gmdate('c', filemtime($file)),
            ];
        }
        usort($items, function ($a, $b) {
            return strcmp($b['createdAt'], $a['createdAt']);
        });
        return $items;
    }

    public function restore($name)
    {
        $file = $this->backupPath($name);
        if (!is_file($file)) {
            throw new Modules_NodeManagerPm2_Exception('Backup file not found.');
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data) || empty($data['version'])) {
            throw new Modules_NodeManagerPm2_Exception('Backup file is invalid.');
        }

        Modules_NodeManagerPm2_Store::instance()->importData($data);
        if (!empty($data['ecosystem_configs']) && is_array($data['ecosystem_configs'])) {
            foreach ($data['ecosystem_configs'] as $config) {
                if (empty($config['path']) || !array_key_exists('content', $config)) {
                    continue;
                }
                $dir = dirname($config['path']);
                if (is_dir($dir) && realpath($dir) !== false) {
                    file_put_contents($config['path'], Modules_NodeManagerPm2_Validator::ecosystemContent($config['content']), LOCK_EX);
                    @chmod($config['path'], 0640);
                }
            }
        }

        return ['restored' => true, 'file' => $file];
    }

    private function backupPath($name)
    {
        $name = basename((string) $name);
        if (!preg_match('/^node-manager-pm2-[0-9]{8}-[0-9]{6}\.json$/', $name)) {
            throw new Modules_NodeManagerPm2_Exception('Backup name is invalid.');
        }
        return Modules_NodeManagerPm2_Config::varDir() . '/backups/' . $name;
    }
}
