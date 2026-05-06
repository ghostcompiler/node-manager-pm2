<?php
class Modules_NodeManagerPm2_Store
{
    private static $instance;
    private $pdo;

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function close()
    {
        $this->pdo = null;
    }

    public function initialize()
    {
        $this->pdo();
        $schema = [
            "CREATE TABLE IF NOT EXISTS settings (
                name TEXT PRIMARY KEY,
                value TEXT,
                updated_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS apps (
                id TEXT PRIMARY KEY,
                domain_id INTEGER NOT NULL,
                domain_name TEXT NOT NULL,
                name TEXT NOT NULL,
                pm2_name TEXT NOT NULL,
                script_path TEXT,
                cwd TEXT,
                ecosystem_path TEXT,
                env_name TEXT NOT NULL DEFAULT 'production',
                instances INTEGER NOT NULL DEFAULT 1,
                autorestart INTEGER NOT NULL DEFAULT 1,
                max_restarts INTEGER,
                restart_delay INTEGER,
                git_repo TEXT,
                git_branch TEXT,
                webhook_token_hash TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(domain_id, pm2_name)
            )",
            "CREATE TABLE IF NOT EXISTS env_vars (
                id TEXT PRIMARY KEY,
                app_id TEXT NOT NULL,
                name TEXT NOT NULL,
                value_encrypted TEXT,
                is_secret INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(app_id, name)
            )",
            "CREATE TABLE IF NOT EXISTS metrics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                app_id TEXT,
                domain_id INTEGER NOT NULL,
                pm2_name TEXT NOT NULL,
                cpu REAL NOT NULL DEFAULT 0,
                memory INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL,
                restarts INTEGER NOT NULL DEFAULT 0,
                instances INTEGER NOT NULL DEFAULT 1,
                collected_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS deployments (
                id TEXT PRIMARY KEY,
                app_id TEXT,
                domain_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                message TEXT,
                output TEXT,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS alerts (
                id TEXT PRIMARY KEY,
                app_id TEXT,
                domain_id INTEGER NOT NULL,
                level TEXT NOT NULL,
                message TEXT NOT NULL,
                read_at TEXT,
                created_at TEXT NOT NULL
            )"
        ];

        foreach ($schema as $statement) {
            $this->pdo->exec($statement);
        }
    }

    public function getSetting($name, $default = null)
    {
        $stmt = $this->pdo()->prepare('SELECT value FROM settings WHERE name = :name');
        $stmt->execute([':name' => $name]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : $value;
    }

    public function setSetting($name, $value)
    {
        $stmt = $this->pdo()->prepare('REPLACE INTO settings (name, value, updated_at) VALUES (:name, :value, :updated_at)');
        $stmt->execute([
            ':name' => $name,
            ':value' => $value,
            ':updated_at' => gmdate('c'),
        ]);
    }

    public function upsertApp($app)
    {
        $now = gmdate('c');
        if (empty($app['id'])) {
            $app['id'] = $this->uuid();
            $app['created_at'] = $now;
        } else {
            $existing = $this->getAppById($app['id']);
            $app['created_at'] = $existing ? $existing['created_at'] : $now;
        }
        $app['updated_at'] = $now;

        $stmt = $this->pdo()->prepare(
            'REPLACE INTO apps (id, domain_id, domain_name, name, pm2_name, script_path, cwd, ecosystem_path, env_name, instances, autorestart, max_restarts, restart_delay, git_repo, git_branch, webhook_token_hash, created_at, updated_at)
             VALUES (:id, :domain_id, :domain_name, :name, :pm2_name, :script_path, :cwd, :ecosystem_path, :env_name, :instances, :autorestart, :max_restarts, :restart_delay, :git_repo, :git_branch, :webhook_token_hash, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':id' => $app['id'],
            ':domain_id' => (int) $app['domain_id'],
            ':domain_name' => $app['domain_name'],
            ':name' => $app['name'],
            ':pm2_name' => $app['pm2_name'],
            ':script_path' => isset($app['script_path']) ? $app['script_path'] : null,
            ':cwd' => isset($app['cwd']) ? $app['cwd'] : null,
            ':ecosystem_path' => isset($app['ecosystem_path']) ? $app['ecosystem_path'] : null,
            ':env_name' => isset($app['env_name']) ? $app['env_name'] : 'production',
            ':instances' => isset($app['instances']) ? (int) $app['instances'] : 1,
            ':autorestart' => !empty($app['autorestart']) ? 1 : 0,
            ':max_restarts' => isset($app['max_restarts']) && $app['max_restarts'] !== '' ? (int) $app['max_restarts'] : null,
            ':restart_delay' => isset($app['restart_delay']) && $app['restart_delay'] !== '' ? (int) $app['restart_delay'] : null,
            ':git_repo' => isset($app['git_repo']) ? $app['git_repo'] : null,
            ':git_branch' => isset($app['git_branch']) ? $app['git_branch'] : null,
            ':webhook_token_hash' => isset($app['webhook_token_hash']) ? $app['webhook_token_hash'] : null,
            ':created_at' => $app['created_at'],
            ':updated_at' => $app['updated_at'],
        ]);

        return $this->getAppById($app['id']);
    }

    public function getAppById($id)
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM apps WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getAppByPm2Name($domainId, $pm2Name)
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM apps WHERE domain_id = :domain_id AND pm2_name = :pm2_name');
        $stmt->execute([
            ':domain_id' => (int) $domainId,
            ':pm2_name' => $pm2Name,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getAppByPm2NameAnyDomain($pm2Name)
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM apps WHERE pm2_name = :pm2_name ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute([':pm2_name' => $pm2Name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getAppByDomainAndPath($domainId, $scriptPath, $cwd = null)
    {
        if (!$scriptPath && !$cwd) {
            return null;
        }

        if ($scriptPath) {
            $stmt = $this->pdo()->prepare('SELECT * FROM apps WHERE domain_id = :domain_id AND script_path = :script_path ORDER BY updated_at DESC LIMIT 1');
            $stmt->execute([
                ':domain_id' => (int) $domainId,
                ':script_path' => $scriptPath,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        if ($cwd) {
            $stmt = $this->pdo()->prepare('SELECT * FROM apps WHERE domain_id = :domain_id AND cwd = :cwd ORDER BY updated_at DESC LIMIT 1');
            $stmt->execute([
                ':domain_id' => (int) $domainId,
                ':cwd' => $cwd,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    public function getAppsByDomain($domainId)
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM apps WHERE domain_id = :domain_id ORDER BY name COLLATE NOCASE');
        $stmt->execute([':domain_id' => (int) $domainId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAppsByDomain($domainId)
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM apps WHERE domain_id = :domain_id');
        $stmt->execute([':domain_id' => (int) $domainId]);
        return (int) $stmt->fetchColumn();
    }

    public function allApps()
    {
        $stmt = $this->pdo()->query('SELECT * FROM apps ORDER BY domain_name COLLATE NOCASE, name COLLATE NOCASE');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteApp($id)
    {
        $this->pdo()->prepare('DELETE FROM env_vars WHERE app_id = :id')->execute([':id' => $id]);
        $this->pdo()->prepare('DELETE FROM apps WHERE id = :id')->execute([':id' => $id]);
    }

    public function listEnv($appId, $includeValues = false)
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM env_vars WHERE app_id = :app_id ORDER BY name COLLATE NOCASE');
        $stmt->execute([':app_id' => $appId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if ($includeValues) {
                $row['value'] = Modules_NodeManagerPm2_Crypto::decrypt($row['value_encrypted']);
            } else {
                $row['value'] = $row['is_secret'] ? null : Modules_NodeManagerPm2_Crypto::decrypt($row['value_encrypted']);
            }
            unset($row['value_encrypted']);
            $row['is_secret'] = (bool) $row['is_secret'];
        }
        return $rows;
    }

    public function upsertEnv($appId, $name, $value, $isSecret)
    {
        $existing = $this->getEnv($appId, $name);
        $now = gmdate('c');
        $stmt = $this->pdo()->prepare(
            'REPLACE INTO env_vars (id, app_id, name, value_encrypted, is_secret, created_at, updated_at)
             VALUES (:id, :app_id, :name, :value_encrypted, :is_secret, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':id' => $existing ? $existing['id'] : $this->uuid(),
            ':app_id' => $appId,
            ':name' => $name,
            ':value_encrypted' => Modules_NodeManagerPm2_Crypto::encrypt($value),
            ':is_secret' => $isSecret ? 1 : 0,
            ':created_at' => $existing ? $existing['created_at'] : $now,
            ':updated_at' => $now,
        ]);
    }

    public function deleteEnv($appId, $name)
    {
        $stmt = $this->pdo()->prepare('DELETE FROM env_vars WHERE app_id = :app_id AND name = :name');
        $stmt->execute([':app_id' => $appId, ':name' => $name]);
    }

    public function getEnv($appId, $name)
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM env_vars WHERE app_id = :app_id AND name = :name');
        $stmt->execute([':app_id' => $appId, ':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function addMetric($metric)
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO metrics (app_id, domain_id, pm2_name, cpu, memory, status, restarts, instances, collected_at)
             VALUES (:app_id, :domain_id, :pm2_name, :cpu, :memory, :status, :restarts, :instances, :collected_at)'
        );
        $stmt->execute([
            ':app_id' => isset($metric['app_id']) ? $metric['app_id'] : null,
            ':domain_id' => (int) $metric['domain_id'],
            ':pm2_name' => $metric['pm2_name'],
            ':cpu' => (float) $metric['cpu'],
            ':memory' => (int) $metric['memory'],
            ':status' => $metric['status'],
            ':restarts' => (int) $metric['restarts'],
            ':instances' => (int) $metric['instances'],
            ':collected_at' => gmdate('c'),
        ]);
    }

    public function metrics($domainId, $pm2Name, $limit)
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM metrics WHERE domain_id = :domain_id AND pm2_name = :pm2_name ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':domain_id', (int) $domainId, PDO::PARAM_INT);
        $stmt->bindValue(':pm2_name', $pm2Name, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function pruneMetrics($days)
    {
        $cutoff = gmdate('c', time() - ((int) $days * 86400));
        $stmt = $this->pdo()->prepare('DELETE FROM metrics WHERE collected_at < :cutoff');
        $stmt->execute([':cutoff' => $cutoff]);
    }

    public function addDeployment($deployment)
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO deployments (id, app_id, domain_id, status, message, output, created_at)
             VALUES (:id, :app_id, :domain_id, :status, :message, :output, :created_at)'
        );
        $stmt->execute([
            ':id' => $this->uuid(),
            ':app_id' => isset($deployment['app_id']) ? $deployment['app_id'] : null,
            ':domain_id' => (int) $deployment['domain_id'],
            ':status' => $deployment['status'],
            ':message' => isset($deployment['message']) ? $deployment['message'] : '',
            ':output' => isset($deployment['output']) ? $deployment['output'] : '',
            ':created_at' => gmdate('c'),
        ]);
    }

    public function recentDeployments($appId, $limit)
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM deployments WHERE app_id = :app_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':app_id', $appId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addAlert($alert)
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO alerts (id, app_id, domain_id, level, message, created_at)
             VALUES (:id, :app_id, :domain_id, :level, :message, :created_at)'
        );
        $stmt->execute([
            ':id' => $this->uuid(),
            ':app_id' => isset($alert['app_id']) ? $alert['app_id'] : null,
            ':domain_id' => (int) $alert['domain_id'],
            ':level' => $alert['level'],
            ':message' => $alert['message'],
            ':created_at' => gmdate('c'),
        ]);
    }

    public function unreadAlerts($limit)
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM alerts WHERE read_at IS NULL ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function exportData()
    {
        return [
            'apps' => $this->allApps(),
            'env_vars' => $this->pdo()->query('SELECT * FROM env_vars')->fetchAll(PDO::FETCH_ASSOC),
            'deployments' => $this->pdo()->query('SELECT * FROM deployments ORDER BY created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC),
            'settings' => $this->pdo()->query('SELECT * FROM settings')->fetchAll(PDO::FETCH_ASSOC),
            'exported_at' => gmdate('c'),
            'version' => '1.0.0',
        ];
    }

    public function importData($data)
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            if (!empty($data['settings']) && is_array($data['settings'])) {
                foreach ($data['settings'] as $row) {
                    if (!empty($row['name']) && array_key_exists($row['name'], Modules_NodeManagerPm2_Config::defaults())) {
                        $this->setSetting($row['name'], isset($row['value']) ? $row['value'] : '');
                    }
                }
            }

            if (!empty($data['apps']) && is_array($data['apps'])) {
                foreach ($data['apps'] as $app) {
                    if (!empty($app['id']) && !empty($app['domain_id']) && !empty($app['pm2_name'])) {
                        $this->upsertApp($app);
                    }
                }
            }

            if (!empty($data['env_vars']) && is_array($data['env_vars'])) {
                $stmt = $pdo->prepare(
                    'REPLACE INTO env_vars (id, app_id, name, value_encrypted, is_secret, created_at, updated_at)
                     VALUES (:id, :app_id, :name, :value_encrypted, :is_secret, :created_at, :updated_at)'
                );
                foreach ($data['env_vars'] as $row) {
                    if (empty($row['app_id']) || empty($row['name'])) {
                        continue;
                    }
                    $stmt->execute([
                        ':id' => !empty($row['id']) ? $row['id'] : $this->uuid(),
                        ':app_id' => $row['app_id'],
                        ':name' => $row['name'],
                        ':value_encrypted' => isset($row['value_encrypted']) ? $row['value_encrypted'] : '',
                        ':is_secret' => !empty($row['is_secret']) ? 1 : 0,
                        ':created_at' => !empty($row['created_at']) ? $row['created_at'] : gmdate('c'),
                        ':updated_at' => gmdate('c'),
                    ]);
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function pdo()
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        if (!class_exists('PDO')) {
            throw new Modules_NodeManagerPm2_Exception('PDO is required for Node Manager (PM2).');
        }

        $dir = Modules_NodeManagerPm2_Config::varDir() . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $this->pdo = new PDO('sqlite:' . $dir . '/node-manager-pm2.sqlite');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->initialize();

        return $this->pdo;
    }

    private function uuid()
    {
        return bin2hex(Modules_NodeManagerPm2_Security::randomBytes(16));
    }
}
