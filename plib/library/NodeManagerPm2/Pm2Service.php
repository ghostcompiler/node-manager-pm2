<?php
class Modules_NodeManagerPm2_Pm2Service
{
    private $runner;
    private $store;
    private $checkingVisibility = false;

    public function __construct()
    {
        $this->runner = new Modules_NodeManagerPm2_CommandRunner();
        $this->store = Modules_NodeManagerPm2_Store::instance();
    }

    public function listProcesses($domain)
    {
        $result = $this->runPm2($domain, $domain->getHomePath(), ['jlist'], [], 30);
        if ($result->code !== 0 && trim($result->stderr . $result->stdout) !== '') {
            throw new Modules_NodeManagerPm2_Exception('Unable to read PM2 process list: ' . trim($result->stderr . $result->stdout));
        }

        $rows = json_decode($result->stdout, true);
        if (!is_array($rows)) {
            $rows = [];
        }

        $groups = [];
        foreach ($rows as $row) {
            $env = isset($row['pm2_env']) && is_array($row['pm2_env']) ? $row['pm2_env'] : [];
            $name = isset($row['name']) ? $row['name'] : (isset($env['name']) ? $env['name'] : 'process-' . (isset($row['pm_id']) ? $row['pm_id'] : 'unknown'));
            $app = $this->appForProcess($domain, $name, $env);
            if (!$this->processBelongsToDomain($domain, $env, $app)) {
                continue;
            }
            if (!isset($groups[$name])) {
                $groups[$name] = [
                    'appId' => $app ? $app['id'] : null,
                    'domainId' => (int) $domain->getId(),
                    'domainName' => $domain->getDisplayName(),
                    'name' => $app ? $app['name'] : $name,
                    'pm2Name' => $name,
                    'scriptPath' => isset($env['pm_exec_path']) ? $env['pm_exec_path'] : (isset($app['script_path']) ? $app['script_path'] : null),
                    'cwd' => isset($env['pm_cwd']) ? $env['pm_cwd'] : (isset($app['cwd']) ? $app['cwd'] : null),
                    'ecosystemPath' => $app ? $app['ecosystem_path'] : null,
                    'envName' => $app ? $app['env_name'] : 'production',
                    'status' => 'stopped',
                    'cpu' => 0,
                    'memory' => 0,
                    'uptime' => null,
                    'restarts' => 0,
                    'instances' => 0,
                    'execMode' => isset($env['exec_mode']) ? $env['exec_mode'] : 'fork_mode',
                    'autorestart' => !isset($env['autorestart']) || (bool) $env['autorestart'],
                    'maxRestarts' => isset($env['max_restarts']) ? $env['max_restarts'] : ($app ? $app['max_restarts'] : null),
                    'restartDelay' => isset($env['restart_delay']) ? $env['restart_delay'] : ($app ? $app['restart_delay'] : null),
                    'pmIds' => [],
                    'logs' => [
                        'out' => isset($env['pm_out_log_path']) ? $env['pm_out_log_path'] : null,
                        'err' => isset($env['pm_err_log_path']) ? $env['pm_err_log_path'] : null,
                    ],
                    'gitRepo' => $app ? $app['git_repo'] : null,
                    'gitBranch' => $app ? $app['git_branch'] : null,
                    'rawStatuses' => [],
                ];
            }

            $groups[$name]['pmIds'][] = isset($row['pm_id']) ? $row['pm_id'] : null;
            $groups[$name]['instances']++;
            $groups[$name]['cpu'] += isset($row['monit']['cpu']) ? (float) $row['monit']['cpu'] : 0;
            $groups[$name]['memory'] += isset($row['monit']['memory']) ? (int) $row['monit']['memory'] : 0;
            $groups[$name]['restarts'] += isset($env['restart_time']) ? (int) $env['restart_time'] : 0;
            $groups[$name]['rawStatuses'][] = isset($env['status']) ? $env['status'] : 'unknown';
            if (!empty($env['pm_uptime'])) {
                $uptime = max(0, time() - ((int) $env['pm_uptime'] / 1000));
                if ($groups[$name]['uptime'] === null || $uptime < $groups[$name]['uptime']) {
                    $groups[$name]['uptime'] = $uptime;
                }
            }
        }

        foreach ($groups as &$group) {
            $group['status'] = $this->aggregateStatus($group['rawStatuses']);
            $group['cpu'] = round($group['cpu'], 2);
            unset($group['rawStatuses']);
            $this->recordMetric($group);
        }

        $this->store->pruneMetrics((int) Modules_NodeManagerPm2_Config::get('metricsRetentionDays', 14));

        return array_values($groups);
    }

    public function createProcess($domain, $input)
    {
        $name = Modules_NodeManagerPm2_Validator::processName($this->value($input, 'name'));
        $script = Modules_NodeManagerPm2_Validator::pathInsideApplicationRoot($domain, $this->value($input, 'scriptPath'), true, 'Script path');
        if (!is_file($script)) {
            throw new Modules_NodeManagerPm2_Exception('Script path must be a file.');
        }

        $cwd = Modules_NodeManagerPm2_Validator::applicationWorkingDirectory($domain, $this->value($input, 'cwd', dirname($script)));
        $envName = Modules_NodeManagerPm2_Validator::envName($this->value($input, 'envName', 'production'));
        $instances = Modules_NodeManagerPm2_Validator::instances($this->value($input, 'instances', 1));
        $autorestart = !array_key_exists('autorestart', $input) || !empty($input['autorestart']);
        $maxRestarts = Modules_NodeManagerPm2_Validator::integerOrNull($this->value($input, 'maxRestarts', null), 0, 1000, 'Max restarts');
        $restartDelay = Modules_NodeManagerPm2_Validator::integerOrNull($this->value($input, 'restartDelay', null), 0, 3600000, 'Restart delay');
        $gitRepo = Modules_NodeManagerPm2_Validator::gitUrl($this->value($input, 'gitRepo', ''));
        $gitBranch = Modules_NodeManagerPm2_Validator::gitRef($this->value($input, 'gitBranch', ''));

        $app = $this->store->upsertApp([
            'domain_id' => $domain->getId(),
            'domain_name' => $domain->getDisplayName(),
            'name' => $name,
            'pm2_name' => $name,
            'script_path' => $script,
            'cwd' => $cwd,
            'env_name' => $envName,
            'instances' => $instances === 'max' ? 0 : $instances,
            'autorestart' => $autorestart,
            'max_restarts' => $maxRestarts,
            'restart_delay' => $restartDelay,
            'git_repo' => $gitRepo,
            'git_branch' => $gitBranch,
        ]);

        if (!empty($input['env']) && is_array($input['env'])) {
            $envService = new Modules_NodeManagerPm2_EnvService();
            foreach ($input['env'] as $envRow) {
                if (!empty($envRow['name'])) {
                    $envService->save($domain, $app['id'], [
                        'name' => $envRow['name'],
                        'value' => isset($envRow['value']) ? $envRow['value'] : '',
                        'isSecret' => !empty($envRow['isSecret']),
                    ], false);
                }
            }
        }

        $args = ['start', $script, '--name', $name, '--time', '--update-env'];
        if ($instances === 'max' || (int) $instances > 1) {
            $args[] = '-i';
            $args[] = (string) $instances;
        }
        if (!$autorestart) {
            $args[] = '--no-autorestart';
        }
        if ($maxRestarts !== null) {
            $args[] = '--max-restarts';
            $args[] = (string) $maxRestarts;
        }
        if ($restartDelay !== null) {
            $args[] = '--restart-delay';
            $args[] = (string) $restartDelay;
        }
        if ($envName) {
            $args[] = '--env';
            $args[] = $envName;
        }

        $this->runPm2($domain, $cwd, $args, $this->environmentForApp($app))->assertOk('Unable to create PM2 process');
        $this->save($domain);
        (new Modules_NodeManagerPm2_EnvService())->syncEcosystem($domain, $app['id']);

        return $app;
    }

    public function action($domain, $pm2Name, $action, $options = [])
    {
        $pm2Name = Modules_NodeManagerPm2_Validator::processName($pm2Name);
        $allowed = ['start', 'stop', 'restart', 'reload', 'delete'];
        if (!in_array($action, $allowed, true)) {
            throw new Modules_NodeManagerPm2_Exception('Unsupported PM2 action.');
        }
        $this->requireProcessVisible($domain, $pm2Name);

        $app = $this->store->getAppByPm2Name($domain->getId(), $pm2Name);
        $cwd = $app && $app['cwd'] ? $app['cwd'] : $domain->getHomePath();
        $env = $app ? $this->environmentForApp($app) : [];

        $args = [$action, $pm2Name];
        if ($action === 'restart' || $action === 'reload') {
            $args[] = '--update-env';
        }

        $this->runPm2($domain, $cwd, $args, $env)->assertOk('Unable to ' . $action . ' process');

        if ($action === 'delete') {
            if ($app) {
                $this->store->deleteApp($app['id']);
            }
            $this->save($domain);
        }

        return ['action' => $action, 'pm2Name' => $pm2Name];
    }

    public function scale($domain, $pm2Name, $instances)
    {
        $pm2Name = Modules_NodeManagerPm2_Validator::processName($pm2Name);
        $instances = Modules_NodeManagerPm2_Validator::instances($instances);
        $this->requireProcessVisible($domain, $pm2Name);
        $app = $this->store->getAppByPm2Name($domain->getId(), $pm2Name);
        $cwd = $app && $app['cwd'] ? $app['cwd'] : $domain->getHomePath();

        $this->runPm2($domain, $cwd, ['scale', $pm2Name, (string) $instances])->assertOk('Unable to scale process');
        if ($app) {
            $app['instances'] = $instances === 'max' ? 0 : (int) $instances;
            $this->store->upsertApp($app);
        }

        return ['pm2Name' => $pm2Name, 'instances' => $instances];
    }

    public function save($domain)
    {
        return $this->runPm2($domain, $domain->getHomePath(), ['save'], [])->assertOk('Unable to save PM2 state');
    }

    public function startupCommand($domain)
    {
        $result = $this->runPm2($domain, $domain->getHomePath(), ['startup'], []);
        return trim($result->stdout . "\n" . $result->stderr);
    }

    public function ecosystemPath($domain, $app)
    {
        $dir = rtrim($domain->getHomePath(), '/') . '/.node-manager-pm2';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir . '/' . preg_replace('/[^A-Za-z0-9._-]/', '-', $app['pm2_name']) . '.ecosystem.config.js';
    }

    public function writeEcosystem($domain, $appId, $content)
    {
        $app = $this->store->getAppById($appId);
        if (!$app || (int) $app['domain_id'] !== (int) $domain->getId()) {
            throw new Modules_NodeManagerPm2_Exception('Application not found.');
        }

        $content = Modules_NodeManagerPm2_Validator::ecosystemContent($content);
        $path = $this->ecosystemPath($domain, $app);
        file_put_contents($path, $content, LOCK_EX);
        @chmod($path, 0640);

        $app['ecosystem_path'] = $path;
        $this->store->upsertApp($app);

        return ['path' => $path, 'content' => $content];
    }

    public function readEcosystem($domain, $appId)
    {
        $app = $this->store->getAppById($appId);
        if (!$app || (int) $app['domain_id'] !== (int) $domain->getId()) {
            throw new Modules_NodeManagerPm2_Exception('Application not found.');
        }

        $path = $app['ecosystem_path'] ?: $this->ecosystemPath($domain, $app);
        $content = is_file($path) ? file_get_contents($path) : (new Modules_NodeManagerPm2_EnvService())->renderEcosystem($app, $this->store->listEnv($app['id'], true));

        return ['path' => $path, 'content' => $content];
    }

    public function startEcosystem($domain, $appId)
    {
        $app = $this->store->getAppById($appId);
        if (!$app || (int) $app['domain_id'] !== (int) $domain->getId()) {
            throw new Modules_NodeManagerPm2_Exception('Application not found.');
        }
        if (!$app['ecosystem_path'] || !is_file($app['ecosystem_path'])) {
            throw new Modules_NodeManagerPm2_Exception('No ecosystem config is saved for this app.');
        }

        $this->runPm2($domain, $app['cwd'] ?: dirname($app['ecosystem_path']), ['start', $app['ecosystem_path'], '--env', $app['env_name'] ?: 'production', '--update-env'], $this->environmentForApp($app))->assertOk('Unable to start ecosystem config');
        $this->save($domain);
    }

    public function environmentForApp($app)
    {
        $env = [];
        if (!$app || empty($app['id'])) {
            return $env;
        }

        foreach ($this->store->listEnv($app['id'], true) as $row) {
            $env[$row['name']] = $row['value'];
        }

        return $env;
    }

    private function runPm2($domain, $cwd, $args, $env = [], $timeout = 120)
    {
        $invocation = Modules_NodeManagerPm2_RuntimeService::pm2Invocation();
        $commandArgs = array_merge($invocation['argsPrefix'], $args);
        Modules_NodeManagerPm2_Logger::info('Running PM2 command.', [
            'mode' => $invocation['mode'],
            'command' => $invocation['command'],
            'pm2' => $invocation['pm2'],
            'args' => $args,
            'cwd' => $cwd,
            'domainId' => $domain->getId(),
        ]);

        return $this->runner->runDomain($domain, $cwd, $invocation['command'], $commandArgs, $env, '', $timeout);
    }

    private function aggregateStatus($statuses)
    {
        if (!$statuses) {
            return 'stopped';
        }
        if (in_array('errored', $statuses, true)) {
            return 'errored';
        }
        if (in_array('online', $statuses, true) && count(array_unique($statuses)) === 1) {
            return 'online';
        }
        if (in_array('online', $statuses, true)) {
            return 'degraded';
        }
        return $statuses[0];
    }

    private function recordMetric($process)
    {
        $this->store->addMetric([
            'app_id' => $process['appId'],
            'domain_id' => $process['domainId'],
            'pm2_name' => $process['pm2Name'],
            'cpu' => $process['cpu'],
            'memory' => $process['memory'],
            'status' => $process['status'],
            'restarts' => $process['restarts'],
            'instances' => $process['instances'],
        ]);

        if (!in_array($process['status'], ['online', 'stopped'], true)) {
            $this->store->addAlert([
                'app_id' => $process['appId'],
                'domain_id' => $process['domainId'],
                'level' => 'warning',
                'message' => $process['pm2Name'] . ' is ' . $process['status'],
            ]);
        }
    }

    private function appForProcess($domain, $pm2Name, array $env)
    {
        $app = $this->store->getAppByPm2Name($domain->getId(), $pm2Name);
        if ($app) {
            return $app;
        }

        $scriptPath = isset($env['pm_exec_path']) ? $this->normalizePath($env['pm_exec_path']) : null;
        $cwd = isset($env['pm_cwd']) ? $this->normalizePath($env['pm_cwd']) : null;
        $app = $this->store->getAppByDomainAndPath($domain->getId(), $scriptPath, $cwd);
        if ($app) {
            return $app;
        }

        $foreignApp = $this->store->getAppByPm2NameAnyDomain($pm2Name);
        if ($foreignApp && $this->processBelongsToDomain($domain, $env, $foreignApp)) {
            $foreignApp['domain_id'] = $domain->getId();
            $foreignApp['domain_name'] = $domain->getDisplayName();
            return $this->store->upsertApp($foreignApp);
        }

        return null;
    }

    private function requireProcessVisible($domain, $pm2Name)
    {
        if ($this->checkingVisibility) {
            return;
        }

        $this->checkingVisibility = true;
        try {
            foreach ($this->listProcesses($domain) as $process) {
                if ($process['pm2Name'] === $pm2Name) {
                    return;
                }
            }
        } finally {
            $this->checkingVisibility = false;
        }

        throw new Modules_NodeManagerPm2_Exception('Process does not belong to the selected domain.');
    }

    private function processBelongsToDomain($domain, array $env, $app = null)
    {
        $root = $this->normalizePath(Modules_NodeManagerPm2_Validator::defaultWorkingRoot($domain));
        if (!$root) {
            return false;
        }

        $envPaths = [];
        foreach (['pm_exec_path', 'pm_cwd'] as $key) {
            if (!empty($env[$key])) {
                $envPaths[] = $env[$key];
            }
        }
        if ($envPaths) {
            foreach ($envPaths as $path) {
                $normalized = $this->normalizePath($path);
                if ($normalized && $this->insidePath($normalized, $root)) {
                    return true;
                }
            }
            return false;
        }

        $paths = [];
        if ($app) {
            foreach (['script_path', 'cwd'] as $key) {
                if (!empty($app[$key])) {
                    $paths[] = $app[$key];
                }
            }
        }

        foreach ($paths as $path) {
            $normalized = $this->normalizePath($path);
            if ($normalized && $this->insidePath($normalized, $root)) {
                return true;
            }
        }

        return empty($paths) && $app && (int) $app['domain_id'] === (int) $domain->getId();
    }

    private function normalizePath($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $real = realpath($path);
        if ($real !== false) {
            return rtrim($real, '/');
        }

        $dir = realpath(dirname($path));
        if ($dir !== false) {
            return rtrim($dir, '/') . '/' . basename($path);
        }

        return rtrim($path, '/');
    }

    private function insidePath($candidate, $root)
    {
        return $candidate === $root || strpos($candidate, rtrim($root, '/') . '/') === 0;
    }

    private function value($input, $key, $default = null)
    {
        return isset($input[$key]) ? $input[$key] : $default;
    }
}
