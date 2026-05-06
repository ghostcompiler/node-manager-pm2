<?php
class Modules_NodeManagerPm2_DeployService
{
    private $runner;
    private $store;

    public function __construct()
    {
        $this->runner = new Modules_NodeManagerPm2_CommandRunner();
        $this->store = Modules_NodeManagerPm2_Store::instance();
    }

    public function deploy($domain, $appId, $options = [])
    {
        $app = $this->store->getAppById($appId);
        if (!$app || (int) $app['domain_id'] !== (int) $domain->getId()) {
            throw new Modules_NodeManagerPm2_Exception('Application not found.');
        }

        $cwd = Modules_NodeManagerPm2_Validator::workingDirectory($domain, $app['cwd'] ?: dirname($app['script_path']));
        $output = [];
        $status = 'success';
        $message = 'Deployment completed.';

        try {
            $this->gitUpdate($domain, $cwd, $app, $output);
            if (!empty($options['npmInstall'])) {
                $this->npmInstall($domain, $cwd, !empty($options['production']), $output);
            }
            $action = !empty($options['reload']) ? 'reload' : 'restart';
            (new Modules_NodeManagerPm2_Pm2Service())->action($domain, $app['pm2_name'], $action);
        } catch (Exception $e) {
            $status = 'failed';
            $message = $e->getMessage();
            $output[] = $message;
        }

        $this->store->addDeployment([
            'app_id' => $app['id'],
            'domain_id' => $domain->getId(),
            'status' => $status,
            'message' => $message,
            'output' => implode("\n\n", $output),
        ]);

        if ($status !== 'success') {
            throw new Modules_NodeManagerPm2_Exception($message);
        }

        return [
            'status' => $status,
            'message' => $message,
            'output' => $output,
            'history' => $this->store->recentDeployments($app['id'], 10),
        ];
    }

    public function configureWebhook($domain, $appId, $enabled)
    {
        $app = $this->store->getAppById($appId);
        if (!$app || (int) $app['domain_id'] !== (int) $domain->getId()) {
            throw new Modules_NodeManagerPm2_Exception('Application not found.');
        }

        $token = null;
        if ($enabled) {
            $token = Modules_NodeManagerPm2_Security::token();
            $app['webhook_token_hash'] = Modules_NodeManagerPm2_Security::hashToken($token);
        } else {
            $app['webhook_token_hash'] = null;
        }

        $this->store->upsertApp($app);

        return [
            'enabled' => (bool) $enabled,
            'token' => $token,
            'url' => $this->webhookUrl($app['id'], $token),
        ];
    }

    public function deployByWebhook($appId, $token)
    {
        $app = $this->store->getAppById($appId);
        if (!$app || !$app['webhook_token_hash'] || !hash_equals($app['webhook_token_hash'], Modules_NodeManagerPm2_Security::hashToken($token))) {
            throw new Modules_NodeManagerPm2_Exception('Webhook token is invalid.');
        }

        $domain = pm_Domain::getByDomainId((int) $app['domain_id']);
        return $this->deploy($domain, $app['id'], ['npmInstall' => true, 'reload' => true, 'production' => true]);
    }

    private function webhookUrl($appId, $token)
    {
        if (!$token) {
            return null;
        }

        $base = class_exists('pm_Context') ? pm_Context::getBaseUrl() : '/modules/node-manager-pm2/';
        return rtrim($base, '/') . '/public/webhook.php?app=' . rawurlencode($appId) . '&token=' . rawurlencode($token);
    }

    private function gitUpdate($domain, $cwd, $app, &$output)
    {
        $git = Modules_NodeManagerPm2_Config::get('gitBinary', 'git');
        if (is_dir($cwd . '/.git')) {
            $result = $this->runner->runDomain($domain, $cwd, $git, ['fetch', '--all', '--prune'], [], '', 300)->assertOk('Git fetch failed');
            $output[] = trim($result->stdout . $result->stderr);
            if ($app['git_branch']) {
                $result = $this->runner->runDomain($domain, $cwd, $git, ['checkout', $app['git_branch']], [], '', 120)->assertOk('Git checkout failed');
                $output[] = trim($result->stdout . $result->stderr);
            }
            $pullArgs = ['pull', '--ff-only'];
            if ($app['git_branch']) {
                $pullArgs[] = 'origin';
                $pullArgs[] = $app['git_branch'];
            }
            $result = $this->runner->runDomain($domain, $cwd, $git, $pullArgs, [], '', 300)->assertOk('Git pull failed');
            $output[] = trim($result->stdout . $result->stderr);
            return;
        }

        if (!$app['git_repo']) {
            $output[] = 'No Git repository configured; skipped code pull.';
            return;
        }

        $entries = array_diff(scandir($cwd), ['.', '..']);
        if ($entries) {
            throw new Modules_NodeManagerPm2_Exception('Working directory is not a Git repository and is not empty.');
        }

        $args = ['clone'];
        if ($app['git_branch']) {
            $args[] = '--branch';
            $args[] = $app['git_branch'];
        }
        $args[] = $app['git_repo'];
        $args[] = $cwd;
        $result = $this->runner->runDomain($domain, dirname($cwd), $git, $args, [], '', 600)->assertOk('Git clone failed');
        $output[] = trim($result->stdout . $result->stderr);
    }

    private function npmInstall($domain, $cwd, $production, &$output)
    {
        $npm = Modules_NodeManagerPm2_Config::get('npmBinary', 'npm');
        $args = ['install'];
        if ($production) {
            $args[] = '--omit=dev';
        }

        $timeout = (int) Modules_NodeManagerPm2_Config::get('deploymentTimeout', 900);
        $result = $this->runner->runDomain($domain, $cwd, $npm, $args, [], '', $timeout)->assertOk('npm install failed');
        $output[] = trim($result->stdout . $result->stderr);
    }
}
