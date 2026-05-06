<?php
class Modules_NodeManagerPm2_RuntimeService
{
    private $runner;

    public function __construct()
    {
        $this->runner = new Modules_NodeManagerPm2_CommandRunner();
    }

    public function versions($domain)
    {
        $items = [
            'node' => $this->runtimeItem($domain, 'nodeBinary', 'node', ['--version']),
            'npm' => $this->runtimeItem($domain, 'npmBinary', 'npm', ['--version']),
            'pm2' => $this->pm2RuntimeItem($domain),
            'git' => $this->runtimeItem($domain, 'gitBinary', 'git', ['--version']),
        ];

        return [
            'items' => $items,
            'ready' => $items['node']['available'] && $items['npm']['available'] && $items['pm2']['available'],
            'nodeReady' => $items['node']['available'] && $items['npm']['available'],
            'pm2Ready' => $items['pm2']['available'],
            'pm2Home' => $domain->getHomePath() . '/.pm2',
            'detectedPaths' => self::detectedPathEntries(),
            'settings' => Modules_NodeManagerPm2_Config::all(),
        ];
    }

    public function installOrUpdatePm2()
    {
        $npmItem = $this->detectBinary('npm', Modules_NodeManagerPm2_Config::get('npmBinary', 'npm'));
        if (!$npmItem['path']) {
            throw new Modules_NodeManagerPm2_Exception('npm was not found. Install Plesk Node.js support or configure the npm binary path first.');
        }

        $npm = $npmItem['path'];
        $env = [
            'PATH' => dirname($npm) . ':' . implode(':', self::detectedPathEntries()),
            'NPM_CONFIG_UPDATE_NOTIFIER' => 'false',
            'NPM_CONFIG_FUND' => 'false',
        ];

        try {
            Modules_NodeManagerPm2_Logger::info('Running PM2 install helper.', [
                'npm' => $npm,
                'path' => $env['PATH'],
            ]);
            $result = $this->runner->runSbin('pm2-helper', ['install', $npm], $env, '');
            $result->assertOk('Unable to install or update PM2');
        } catch (Modules_NodeManagerPm2_Exception $e) {
            Modules_NodeManagerPm2_Logger::error('PM2 install helper failed.', ['exception' => $e]);
            throw new Modules_NodeManagerPm2_Exception('PM2 installation failed: ' . $e->getMessage());
        } catch (Exception $e) {
            Modules_NodeManagerPm2_Logger::error('PM2 install helper failed unexpectedly.', ['exception' => $e]);
            throw new Modules_NodeManagerPm2_Exception('PM2 installation failed: ' . $e->getMessage());
        }

        $pm2 = $this->detectBinary('pm2', Modules_NodeManagerPm2_Config::get('pm2Binary', 'pm2'));
        $node = $this->detectBinary('node', Modules_NodeManagerPm2_Config::get('nodeBinary', 'node'));
        if ($pm2['path']) {
            Modules_NodeManagerPm2_Config::set('pm2Binary', $pm2['path']);
        }
        Modules_NodeManagerPm2_Config::set('npmBinary', $npm);
        if ($node['path']) {
            Modules_NodeManagerPm2_Config::set('nodeBinary', $node['path']);
        }
        if (!$pm2['path']) {
            Modules_NodeManagerPm2_Logger::error('PM2 install completed but pm2 binary was not detected.', [
                'stdout' => $result->stdout,
                'stderr' => $result->stderr,
                'detectedPaths' => self::detectedPathEntries(),
            ]);
            throw new Modules_NodeManagerPm2_Exception('PM2 install command completed, but the pm2 binary was not detected in the Plesk Node.js paths. Output: ' . trim($result->stdout . ' ' . $result->stderr));
        }

        Modules_NodeManagerPm2_Logger::info('PM2 install completed.', [
            'npm' => $npm,
            'pm2' => $pm2['path'],
            'stdout' => $result->stdout,
            'stderr' => $result->stderr,
        ]);

        return trim($result->stdout . "\n" . $result->stderr);
    }

    public static function pm2Invocation()
    {
        $pm2 = self::detectExecutable('pm2', Modules_NodeManagerPm2_Config::get('pm2Binary', 'pm2'));
        $node = self::detectExecutable('node', Modules_NodeManagerPm2_Config::get('nodeBinary', 'node'));

        if ($node['path'] && $pm2['path']) {
            return [
                'command' => $node['path'],
                'argsPrefix' => [$pm2['path']],
                'pm2' => $pm2['path'],
                'node' => $node['path'],
                'mode' => 'node-launcher',
            ];
        }

        return [
            'command' => Modules_NodeManagerPm2_Config::get('pm2Binary', 'pm2'),
            'argsPrefix' => [],
            'pm2' => $pm2['path'],
            'node' => $node['path'],
            'mode' => 'direct',
        ];
    }

    public function applyDetectedPaths()
    {
        foreach ([
            'nodeBinary' => 'node',
            'npmBinary' => 'npm',
            'pm2Binary' => 'pm2',
            'gitBinary' => 'git',
        ] as $setting => $binary) {
            $detected = $this->detectBinary($binary, Modules_NodeManagerPm2_Config::get($setting, $binary));
            if ($detected['path']) {
                Modules_NodeManagerPm2_Config::set($setting, $detected['path']);
            }
        }

        $paths = array_unique(array_merge(
            explode(':', Modules_NodeManagerPm2_Config::get('extraPath', '')),
            self::detectedPathEntries()
        ));
        $paths = array_values(array_filter($paths, function ($path) {
            return $path !== '' && is_dir($path);
        }));
        Modules_NodeManagerPm2_Config::set('extraPath', implode(':', $paths));

        return Modules_NodeManagerPm2_Config::all();
    }

    public static function detectedPathEntries()
    {
        $paths = ['/usr/local/bin', '/usr/bin', '/bin', '/opt/plesk/node/bin'];
        foreach (glob('/opt/plesk/node/*/bin', GLOB_ONLYDIR) ?: [] as $path) {
            $paths[] = $path;
        }
        foreach (glob('/opt/plesk/nodejs/*/bin', GLOB_ONLYDIR) ?: [] as $path) {
            $paths[] = $path;
        }

        return array_values(array_unique(array_filter($paths, function ($path) {
            return is_dir($path);
        })));
    }

    private function runtimeItem($domain, $setting, $binary, $args)
    {
        $configured = Modules_NodeManagerPm2_Config::get($setting, $binary);
        $detected = $this->detectBinary($binary, $configured);
        $commands = array_values(array_unique(array_filter([$configured, $detected['path'], $binary])));
        $lastError = null;

        foreach ($commands as $command) {
            $result = $this->version($domain, $command, $args);
            if ($result['available']) {
                $result['configured'] = $configured;
                $result['detected'] = $detected['path'];
                $result['path'] = $command;
                $result['setting'] = $setting;
                return $result;
            }
            $lastError = $result['error'];
        }

        return [
            'available' => false,
            'version' => null,
            'error' => $lastError ?: $binary . ' was not found.',
            'configured' => $configured,
            'detected' => $detected['path'],
            'path' => null,
            'setting' => $setting,
        ];
    }

    private function pm2RuntimeItem($domain)
    {
        $configured = Modules_NodeManagerPm2_Config::get('pm2Binary', 'pm2');
        $detected = $this->detectBinary('pm2', $configured);
        $invocation = self::pm2Invocation();
        $lastError = null;

        if ($invocation['command'] && $invocation['pm2']) {
            $result = $this->version($domain, $invocation['command'], array_merge($invocation['argsPrefix'], ['--version']));
            if ($result['available']) {
                $result['configured'] = $configured;
                $result['detected'] = $detected['path'];
                $result['path'] = $invocation['pm2'];
                $result['launcher'] = $invocation['node'];
                $result['setting'] = 'pm2Binary';
                return $result;
            }
            $lastError = $result['error'];
        }

        foreach (array_values(array_unique(array_filter([$configured, $detected['path'], 'pm2']))) as $command) {
            $result = $this->version($domain, $command, ['--version']);
            if ($result['available']) {
                $result['configured'] = $configured;
                $result['detected'] = $detected['path'];
                $result['path'] = $command;
                $result['launcher'] = null;
                $result['setting'] = 'pm2Binary';
                return $result;
            }
            $lastError = $result['error'];
        }

        return [
            'available' => false,
            'version' => null,
            'error' => $lastError ?: 'pm2 was not found.',
            'configured' => $configured,
            'detected' => $detected['path'],
            'path' => null,
            'launcher' => $invocation['node'],
            'setting' => 'pm2Binary',
        ];
    }

    private function detectBinary($binary, $configured)
    {
        return self::detectExecutable($binary, $configured);
    }

    private static function detectExecutable($binary, $configured)
    {
        $candidates = [];
        if ($configured !== null && $configured !== '' && $configured[0] === '/') {
            $candidates[] = $configured;
        }

        foreach (self::detectedPathEntries() as $path) {
            $candidates[] = rtrim($path, '/') . '/' . $binary;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return ['path' => $candidate];
            }
        }

        return ['path' => null];
    }

    private function version($domain, $command, $args)
    {
        try {
            $result = $this->runner->runDomain($domain, $domain->getHomePath(), $command, $args, [], '', 20);
            if ($result->code !== 0) {
                return ['available' => false, 'version' => null, 'error' => trim($result->stderr . ' ' . $result->stdout)];
            }
            return ['available' => true, 'version' => trim($result->stdout), 'error' => null];
        } catch (Exception $e) {
            return ['available' => false, 'version' => null, 'error' => $e->getMessage()];
        }
    }
}
