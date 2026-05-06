<?php
class Modules_NodeManagerPm2_BrowseService
{
    public function browse($domain, $path, $mode)
    {
        $home = realpath($domain->getHomePath());
        if ($home === false) {
            throw new Modules_NodeManagerPm2_Exception('Subscription home directory is not available.');
        }

        $root = realpath(Modules_NodeManagerPm2_Validator::defaultWorkingRoot($domain));
        if ($root === false || !$this->inside($root, $home)) {
            $root = $home;
        }

        $current = $this->resolve($path, $root);
        if (is_file($current)) {
            $current = dirname($current);
        }
        if (!is_dir($current) || !$this->inside($current, $root)) {
            $current = $root;
        }

        $entries = [];
        foreach (scandir($current) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $fullPath = $current . '/' . $name;
            $realPath = realpath($fullPath);
            if ($realPath === false || !$this->inside($realPath, $root)) {
                continue;
            }

            $isDir = is_dir($realPath);
            $value = $this->valueFor($realPath, $root);
            $entries[] = [
                'name' => $name,
                'type' => $isDir ? 'directory' : 'file',
                'path' => $realPath,
                'value' => $value,
                'selectable' => $mode === 'directory' ? $isDir : !$isDir,
                'editable' => !$isDir && $this->isEditable($realPath),
                'size' => $isDir ? null : filesize($realPath),
                'modifiedAt' => gmdate('c', filemtime($realPath)),
            ];
        }

        usort($entries, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'homePath' => $home,
            'rootPath' => $root,
            'currentPath' => $current,
            'currentValue' => $this->valueFor($current, $root),
            'parentValue' => $current === $root ? null : $this->valueFor(dirname($current), $root),
            'mode' => $mode === 'directory' ? 'directory' : 'file',
            'entries' => array_slice($entries, 0, 500),
        ];
    }

    public function readFile($domain, $path)
    {
        $file = $this->resolveFile($domain, $path);
        if (!$this->isEditable($file)) {
            throw new Modules_NodeManagerPm2_Exception('This file type cannot be edited here.');
        }
        if (filesize($file) > 1048576) {
            throw new Modules_NodeManagerPm2_Exception('Files larger than 1 MB cannot be edited here.');
        }

        return [
            'path' => $file,
            'value' => $this->valueFor($file, $this->root($domain)),
            'content' => file_get_contents($file),
            'modifiedAt' => gmdate('c', filemtime($file)),
        ];
    }

    public function saveFile($domain, $path, $content)
    {
        $file = $this->resolveFile($domain, $path);
        if (!$this->isEditable($file)) {
            throw new Modules_NodeManagerPm2_Exception('This file type cannot be edited here.');
        }
        if (strlen((string) $content) > 1048576) {
            throw new Modules_NodeManagerPm2_Exception('Files larger than 1 MB cannot be edited here.');
        }
        if (file_put_contents($file, (string) $content, LOCK_EX) === false) {
            throw new Modules_NodeManagerPm2_Exception('Unable to save file.');
        }

        return $this->readFile($domain, $path);
    }

    private function resolve($path, $root)
    {
        $path = trim((string) $path);
        if ($path === '' || $path === '.') {
            return $root;
        }

        $candidate = null;
        if ($path[0] === '/') {
            $candidate = realpath($path);
        } else {
            foreach ([$root . '/' . ltrim($path, '/')] as $fullPath) {
                $candidate = realpath($fullPath);
                if ($candidate !== false) {
                    break;
                }
            }
        }

        if ($candidate === false || $candidate === null || !$this->inside($candidate, $root)) {
            return $root;
        }

        return $candidate;
    }

    private function valueFor($path, $root)
    {
        if ($path === $root) {
            return '.';
        }
        if (strpos($path, $root . '/') === 0) {
            return substr($path, strlen($root) + 1);
        }
        return $path;
    }

    private function resolveFile($domain, $path)
    {
        $root = $this->root($domain);
        $file = $this->resolve($path, $root);
        if (!is_file($file) || !$this->inside($file, $root)) {
            throw new Modules_NodeManagerPm2_Exception('File is outside the domain document root.');
        }

        return $file;
    }

    private function root($domain)
    {
        $home = realpath($domain->getHomePath());
        if ($home === false) {
            throw new Modules_NodeManagerPm2_Exception('Subscription home directory is not available.');
        }

        $root = realpath(Modules_NodeManagerPm2_Validator::defaultWorkingRoot($domain));
        if ($root === false || !$this->inside($root, $home)) {
            return $home;
        }

        return $root;
    }

    private function isEditable($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $editable = [
            'conf', 'config', 'css', 'env', 'htaccess', 'html', 'ini', 'js', 'json',
            'log', 'md', 'mjs', 'php', 'phtml', 'sh', 'sql', 'txt', 'xml', 'yaml', 'yml',
        ];

        return in_array($extension, $editable, true) || strpos(basename($path), '.') === 0;
    }

    private function inside($candidate, $home)
    {
        return $candidate === $home || strpos($candidate, $home . '/') === 0;
    }
}
