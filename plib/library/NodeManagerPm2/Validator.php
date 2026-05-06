<?php
class Modules_NodeManagerPm2_Validator
{
    public static function processName($name)
    {
        $name = trim((string) $name);
        if ($name === '' || strlen($name) > 80 || !preg_match('/^[A-Za-z0-9._:-]+$/', $name)) {
            throw new Modules_NodeManagerPm2_Exception('Process name may contain letters, numbers, dots, underscores, colons, and dashes only.');
        }
        return $name;
    }

    public static function envName($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'production';
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new Modules_NodeManagerPm2_Exception('Environment name is invalid.');
        }
        return $name;
    }

    public static function envVarName($name)
    {
        $name = trim((string) $name);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new Modules_NodeManagerPm2_Exception('Environment variable names must be valid shell identifiers.');
        }
        return $name;
    }

    public static function instances($instances)
    {
        if ($instances === 'max') {
            return 'max';
        }

        $instances = (int) $instances;
        if ($instances < 1 || $instances > 64) {
            throw new Modules_NodeManagerPm2_Exception('Instances must be between 1 and 64, or max.');
        }
        return $instances;
    }

    public static function integerOrNull($value, $min, $max, $label)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (int) $value;
        if ($value < $min || $value > $max) {
            throw new Modules_NodeManagerPm2_Exception($label . ' is out of range.');
        }
        return $value;
    }

    public static function pathInsideDomain($domain, $path, $mustExist, $label)
    {
        $path = trim((string) $path);
        if ($path === '') {
            throw new Modules_NodeManagerPm2_Exception($label . ' is required.');
        }

        $home = self::normalizeExisting($domain->getHomePath());
        if ($path[0] !== '/') {
            $candidate = self::relativePathInsideDomain($domain, $path, $mustExist, $home);
        } else {
            $candidate = $mustExist ? self::normalizeExisting($path) : self::normalizeFuture($path);
        }

        if ($candidate === false || strpos($candidate, $home . '/') !== 0 && $candidate !== $home) {
            throw new Modules_NodeManagerPm2_Exception($label . ' must be inside the subscription home directory.');
        }

        if ($mustExist && !file_exists($candidate)) {
            throw new Modules_NodeManagerPm2_Exception($label . ' does not exist.');
        }

        return $candidate;
    }

    public static function workingDirectory($domain, $path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return self::defaultWorkingRoot($domain);
        }
        $dir = self::pathInsideDomain($domain, $path, true, 'Working directory');
        if (!is_dir($dir)) {
            throw new Modules_NodeManagerPm2_Exception('Working directory must be a directory.');
        }
        return $dir;
    }

    public static function pathInsideApplicationRoot($domain, $path, $mustExist, $label)
    {
        $path = trim((string) $path);
        if ($path === '') {
            throw new Modules_NodeManagerPm2_Exception($label . ' is required.');
        }

        $root = self::normalizeExisting(self::defaultWorkingRoot($domain));
        if ($root === false) {
            throw new Modules_NodeManagerPm2_Exception('Application root is not available for the selected domain.');
        }

        if ($path[0] === '/') {
            $candidate = $mustExist ? self::normalizeExisting($path) : self::normalizeFuture($path);
        } else {
            $candidatePath = rtrim($root, '/') . '/' . ltrim($path, '/');
            $candidate = $mustExist ? self::normalizeExisting($candidatePath) : self::normalizeFuture($candidatePath);
        }

        if ($candidate === false || !self::isInside($candidate, $root)) {
            throw new Modules_NodeManagerPm2_Exception($label . ' must be inside the selected domain application root.');
        }

        if ($mustExist && !file_exists($candidate)) {
            throw new Modules_NodeManagerPm2_Exception($label . ' does not exist.');
        }

        return $candidate;
    }

    public static function applicationWorkingDirectory($domain, $path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return self::defaultWorkingRoot($domain);
        }
        $dir = self::pathInsideApplicationRoot($domain, $path, true, 'Working directory');
        if (!is_dir($dir)) {
            throw new Modules_NodeManagerPm2_Exception('Working directory must be a directory.');
        }
        return $dir;
    }

    public static function defaultWorkingRoot($domain)
    {
        $home = self::normalizeExisting($domain->getHomePath());
        if ($home === false) {
            return $domain->getHomePath();
        }

        $documentRoot = '';
        if (method_exists($domain, 'getDocumentRoot')) {
            $documentRoot = (string) $domain->getDocumentRoot();
        }

        $documentRootPath = self::absoluteDomainPath($domain, $documentRoot);
        $realDocumentRoot = $documentRootPath ? self::normalizeExisting($documentRootPath) : false;

        $names = [];
        foreach (['getName', 'getDisplayName'] as $method) {
            if (method_exists($domain, $method)) {
                $names[] = $domain->$method();
            }
        }

        $candidates = [];
        foreach (array_unique(array_filter($names)) as $name) {
            $candidates[] = rtrim($domain->getHomePath(), '/') . '/' . trim($name, '/');
        }
        if ($realDocumentRoot !== false) {
            $candidates[] = $realDocumentRoot;
        }
        $candidates[] = rtrim($domain->getHomePath(), '/') . '/httpdocs';

        foreach (array_unique($candidates) as $candidate) {
            $realRoot = self::normalizeExisting($candidate);
            if ($realRoot !== false && self::isInside($realRoot, $home)) {
                return $realRoot;
            }
        }

        return $domain->getHomePath();
    }

    public static function ecosystemContent($content)
    {
        $content = (string) $content;
        if (strlen($content) > 524288) {
            throw new Modules_NodeManagerPm2_Exception('Ecosystem config is too large.');
        }
        if (strpos($content, '<?') !== false) {
            throw new Modules_NodeManagerPm2_Exception('Ecosystem config cannot contain PHP tags.');
        }
        return $content;
    }

    public static function gitRef($ref)
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9._\/-]+$/', $ref) || strpos($ref, '..') !== false) {
            throw new Modules_NodeManagerPm2_Exception('Git branch/ref is invalid.');
        }
        return $ref;
    }

    public static function gitUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('/^(https:\/\/|ssh:\/\/|git@)[^\s]+$/', $url)) {
            throw new Modules_NodeManagerPm2_Exception('Git repository URL must use https, ssh, or git@ syntax.');
        }
        return $url;
    }

    private static function normalizeExisting($path)
    {
        return realpath($path);
    }

    private static function relativePathInsideDomain($domain, $path, $mustExist, $home)
    {
        $relative = ltrim($path, '/');
        $bases = array_values(array_unique([
            self::defaultWorkingRoot($domain),
            $domain->getHomePath(),
        ]));

        foreach ($bases as $base) {
            $candidatePath = rtrim($base, '/') . '/' . $relative;
            $candidate = $mustExist ? self::normalizeExisting($candidatePath) : self::normalizeFuture($candidatePath);
            if ($candidate !== false && ($candidate === $home || strpos($candidate, $home . '/') === 0)) {
                return $candidate;
            }
        }

        return false;
    }

    private static function absoluteDomainPath($domain, $path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        if ($path[0] === '/') {
            return $path;
        }

        return rtrim($domain->getHomePath(), '/') . '/' . ltrim($path, '/');
    }

    private static function isInside($candidate, $home)
    {
        return $candidate === $home || strpos($candidate, $home . '/') === 0;
    }

    private static function normalizeFuture($path)
    {
        $dir = dirname($path);
        $base = basename($path);
        $realDir = realpath($dir);
        if ($realDir === false) {
            return false;
        }
        return rtrim($realDir, '/') . '/' . $base;
    }
}
