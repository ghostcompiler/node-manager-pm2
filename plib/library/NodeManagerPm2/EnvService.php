<?php
class Modules_NodeManagerPm2_EnvService
{
    private $store;

    public function __construct()
    {
        $this->store = Modules_NodeManagerPm2_Store::instance();
    }

    public function listForApp($domain, $appId)
    {
        $app = $this->requireApp($domain, $appId);
        return $this->store->listEnv($app['id'], false);
    }

    public function save($domain, $appId, $input, $restart = true)
    {
        $app = $this->requireApp($domain, $appId);
        $name = Modules_NodeManagerPm2_Validator::envVarName(isset($input['name']) ? $input['name'] : '');
        $value = isset($input['value']) ? (string) $input['value'] : '';
        $isSecret = !empty($input['isSecret']);
        $this->store->upsertEnv($app['id'], $name, $value, $isSecret);
        $this->syncEcosystem($domain, $app['id']);

        if ($restart) {
            (new Modules_NodeManagerPm2_Pm2Service())->action($domain, $app['pm2_name'], 'restart');
        }
    }

    public function delete($domain, $appId, $name, $restart = true)
    {
        $app = $this->requireApp($domain, $appId);
        $name = Modules_NodeManagerPm2_Validator::envVarName($name);
        $this->store->deleteEnv($app['id'], $name);
        $this->syncEcosystem($domain, $app['id']);

        if ($restart) {
            (new Modules_NodeManagerPm2_Pm2Service())->action($domain, $app['pm2_name'], 'restart');
        }
    }

    public function syncEcosystem($domain, $appId)
    {
        $app = $this->requireApp($domain, $appId);
        if (empty($app['script_path'])) {
            return;
        }

        $pm2 = new Modules_NodeManagerPm2_Pm2Service();
        $pm2->writeEcosystem($domain, $app['id'], $this->renderEcosystem($app, $this->store->listEnv($app['id'], true)));
    }

    public function renderEcosystem($app, $envRows)
    {
        $env = [];
        foreach ($envRows as $row) {
            $env[$row['name']] = $row['value'];
        }

        $envName = $app['env_name'] ?: 'production';
        $instances = (int) $app['instances'] > 0 ? (int) $app['instances'] : 'max';
        $config = [
            'apps' => [[
                'name' => $app['pm2_name'],
                'script' => $app['script_path'],
                'cwd' => $app['cwd'] ?: dirname($app['script_path']),
                'exec_mode' => ($instances === 'max' || $instances > 1) ? 'cluster' : 'fork',
                'instances' => $instances,
                'autorestart' => (bool) $app['autorestart'],
                'max_restarts' => $app['max_restarts'] === null ? null : (int) $app['max_restarts'],
                'restart_delay' => $app['restart_delay'] === null ? null : (int) $app['restart_delay'],
                'env' => $env,
                'env_' . $envName => $env,
            ]],
        ];

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return "module.exports = " . $json . ";\n";
    }

    private function requireApp($domain, $appId)
    {
        $app = $this->store->getAppById($appId);
        if (!$app || (int) $app['domain_id'] !== (int) $domain->getId()) {
            throw new Modules_NodeManagerPm2_Exception('Application not found.');
        }
        return $app;
    }
}
