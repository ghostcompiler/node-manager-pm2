<?php
class Modules_NodeManagerPm2_CommandRunner
{
    public function runDomain($domain, $workingDir, $command, $args = [], $env = [], $stdin = '', $timeout = 60)
    {
        $this->validateCommand($command);
        $workingDir = Modules_NodeManagerPm2_Validator::workingDirectory($domain, $workingDir);
        $env = $this->domainEnv($domain, $env);

        if (class_exists('pm_ApiCli')) {
            try {
                $result = pm_ApiCli::callDomain($domain, $workingDir, $command, $args, pm_ApiCli::RESULT_FULL, $env, $stdin);
                return $this->apiResult($result, $this->displayCommand($command, $args));
            } catch (Exception $e) {
                throw new Modules_NodeManagerPm2_Exception($e->getMessage());
            }
        }

        return $this->runLocal($workingDir, $command, $args, $env, $stdin, $timeout);
    }

    public function runSbin($command, $args = [], $env = [], $stdin = '')
    {
        $this->validateCommand($command);
        if (class_exists('pm_ApiCli')) {
            try {
                $result = pm_ApiCli::callSbin($command, $args, pm_ApiCli::RESULT_FULL, $env);
                return $this->apiResult($result, $this->displayCommand($command, $args));
            } catch (Exception $e) {
                throw new Modules_NodeManagerPm2_Exception($e->getMessage());
            }
        }

        $sbin = dirname(__DIR__, 3) . '/sbin/' . $command;
        return $this->runLocal(dirname(__DIR__, 3), $sbin, $args, $env, $stdin, 300);
    }

    public function domainEnv($domain, $env = [])
    {
        $home = $domain->getHomePath();
        $path = Modules_NodeManagerPm2_Config::get('extraPath', '');
        $detectedPaths = Modules_NodeManagerPm2_RuntimeService::detectedPathEntries();
        if ($detectedPaths) {
            $path = trim($path . ':' . implode(':', $detectedPaths), ':');
        }
        $currentPath = getenv('PATH');
        $mergedPath = trim($path . ($path && $currentPath ? ':' : '') . $currentPath, ':');

        return $env + [
            'HOME' => $home,
            'USER' => $domain->getSysUserLogin(),
            'LOGNAME' => $domain->getSysUserLogin(),
            'PM2_HOME' => $home . '/.pm2',
            'PATH' => $mergedPath,
        ];
    }

    private function runLocal($workingDir, $command, $args, $env, $stdin, $timeout)
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $commandLine = $this->shellCommand($command, $args);
        $process = proc_open($commandLine, $descriptorSpec, $pipes, $workingDir, $env);
        if (!is_resource($process)) {
            throw new Modules_NodeManagerPm2_Exception('Unable to start command process.');
        }

        fwrite($pipes[0], (string) $stdin);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $started = time();
        $exitCode = null;
        while (true) {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                $exitCode = isset($status['exitcode']) ? $status['exitcode'] : null;
                break;
            }

            if (time() - $started > $timeout) {
                proc_terminate($process);
                throw new Modules_NodeManagerPm2_Exception('Command timed out.');
            }

            usleep(100000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code === -1 && $exitCode !== null) {
            $code = $exitCode;
        }

        return new Modules_NodeManagerPm2_CommandResult($code, $stdout, $stderr, $this->displayCommand($command, $args));
    }

    private function shellCommand($command, $args)
    {
        $parts = [escapeshellcmd($command)];
        foreach ($args as $arg) {
            $parts[] = escapeshellarg((string) $arg);
        }
        return implode(' ', $parts);
    }

    private function displayCommand($command, $args)
    {
        return $command . ' ' . implode(' ', array_map(function ($arg) {
            return (string) $arg;
        }, $args));
    }

    private function apiResult($result, $displayCommand)
    {
        if (is_string($result)) {
            return new Modules_NodeManagerPm2_CommandResult(0, $result, '', $displayCommand);
        }

        if (!is_array($result)) {
            return new Modules_NodeManagerPm2_CommandResult(0, (string) $result, '', $displayCommand);
        }

        return new Modules_NodeManagerPm2_CommandResult(
            (int) $this->arrayValue($result, ['code', 'exitCode', 'exit_code', 0], 0),
            (string) $this->arrayValue($result, ['stdout', 'out', 1], ''),
            (string) $this->arrayValue($result, ['stderr', 'err', 2], ''),
            $displayCommand
        );
    }

    private function arrayValue(array $array, array $keys, $default)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                return $array[$key];
            }
        }

        return $default;
    }

    private function validateCommand($command)
    {
        if (!preg_match('/^[A-Za-z0-9_\/.\-]+$/', $command)) {
            throw new Modules_NodeManagerPm2_Exception('Command path is invalid.');
        }
    }
}
