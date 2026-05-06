<?php
class Modules_NodeManagerPm2_LogService
{
    private $pm2;

    public function __construct()
    {
        $this->pm2 = new Modules_NodeManagerPm2_Pm2Service();
    }

    public function read($domain, $pm2Name, $stream, $bytes)
    {
        $paths = $this->paths($domain, $pm2Name);
        $path = $this->selectPath($paths, $stream);
        $bytes = max(1024, min((int) $bytes, (int) Modules_NodeManagerPm2_Config::get('maxLogBytes', 200000)));

        if (!$path || !is_file($path)) {
            return ['content' => '', 'size' => 0, 'path' => $path, 'truncated' => false];
        }

        $size = filesize($path);
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new Modules_NodeManagerPm2_Exception('Unable to read log file.');
        }
        $offset = max(0, $size - $bytes);
        fseek($handle, $offset);
        $content = stream_get_contents($handle);
        fclose($handle);

        return [
            'content' => $content,
            'size' => $size,
            'path' => $path,
            'truncated' => $offset > 0,
        ];
    }

    public function clear($domain, $pm2Name, $stream)
    {
        $paths = $this->paths($domain, $pm2Name);
        foreach ($this->matchingPaths($paths, $stream) as $path) {
            if ($path && is_file($path)) {
                $this->truncate($domain, $path);
            }
        }
    }

    public function download($domain, $pm2Name, $stream)
    {
        $paths = $this->paths($domain, $pm2Name);
        $path = $this->selectPath($paths, $stream);
        if (!$path || !is_file($path)) {
            throw new Modules_NodeManagerPm2_Exception('Log file not found.');
        }

        return $path;
    }

    public function paths($domain, $pm2Name)
    {
        $pm2Name = Modules_NodeManagerPm2_Validator::processName($pm2Name);
        $processes = $this->pm2->listProcesses($domain);
        foreach ($processes as $process) {
            if ($process['pm2Name'] === $pm2Name) {
                return [
                    'out' => $this->safeLogPath($domain, $process['logs']['out']),
                    'err' => $this->safeLogPath($domain, $process['logs']['err']),
                ];
            }
        }

        throw new Modules_NodeManagerPm2_Exception('Process not found.');
    }

    private function safeLogPath($domain, $path)
    {
        if (!$path) {
            return null;
        }

        $real = realpath($path);
        $home = realpath($domain->getHomePath());
        if ($real === false || $home === false || strpos($real, $home . '/') !== 0) {
            throw new Modules_NodeManagerPm2_Exception('PM2 returned a log path outside the subscription.');
        }

        return $real;
    }

    private function selectPath($paths, $stream)
    {
        $stream = $stream === 'stderr' || $stream === 'err' ? 'err' : 'out';
        return isset($paths[$stream]) ? $paths[$stream] : null;
    }

    private function matchingPaths($paths, $stream)
    {
        if ($stream === 'all') {
            return array_values($paths);
        }
        return [$this->selectPath($paths, $stream)];
    }

    private function truncate($domain, $path)
    {
        $handle = @fopen($path, 'r+b');
        if ($handle) {
            if (!flock($handle, LOCK_EX)) {
                fclose($handle);
                throw new Modules_NodeManagerPm2_Exception('Unable to lock log file for clearing.');
            }
            ftruncate($handle, 0);
            fflush($handle);
            flock($handle, LOCK_UN);
            fclose($handle);
            return;
        }

        $result = (new Modules_NodeManagerPm2_CommandRunner())->runDomain(
            $domain,
            $domain->getHomePath(),
            '/bin/sh',
            ['-lc', ': > "$1"', 'node-manager-pm2-clear-log', $path],
            [],
            '',
            15
        );

        if ($result->code !== 0) {
            throw new Modules_NodeManagerPm2_Exception('Unable to clear log file: ' . trim($result->stderr . $result->stdout));
        }
    }
}
