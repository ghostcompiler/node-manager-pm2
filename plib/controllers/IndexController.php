<?php
require_once dirname(__DIR__) . '/library/NodeManagerPm2/Autoload.php';
Modules_NodeManagerPm2_Autoload::register();

class IndexController extends pm_Controller_Action
{
    protected $_accessLevel = ['admin', 'reseller', 'client'];

    private $domains;

    public function init()
    {
        parent::init();
        Modules_NodeManagerPm2_Store::instance()->initialize();
        Modules_NodeManagerPm2_Config::ensureDefaults();
        $this->domains = new Modules_NodeManagerPm2_DomainRepository();
        $this->view->pageTitle = $this->lmsg('page_title');
        $this->view->extensionInfo = $this->extensionInfo();
        $assetVersion = rawurlencode($this->assetVersion());
        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl() . 'css/app.css?v=' . $assetVersion);
    }

    public function indexAction()
    {
        $domainId = $this->requestedDomainId();
        $this->view->appConfig = [
            'csrf' => Modules_NodeManagerPm2_Security::csrfToken(),
            'endpoints' => $this->endpoints(),
            'initialDomainId' => $domainId,
            'domainContextId' => $domainId,
            'domainLocked' => $domainId !== null,
            'title' => 'Node Manager (PM2)',
            'info' => $this->extensionInfo(),
            'infoUrl' => $this->infoUrl($domainId),
        ];
    }

    public function infoAction()
    {
        $domainId = $this->requestedDomainId();
        $this->view->domainId = $domainId;
        $this->view->backUrl = $this->indexUrl($domainId);
    }

    public function domainAction()
    {
        $domainId = $this->requestedDomainId();
        if ($domainId === null || $domainId === '') {
            throw new Modules_NodeManagerPm2_Exception('Unable to detect the selected domain. Open Node Manager (PM2) from the domain card again.');
        }

        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
        $this->getResponse()->setRedirect(pm_Context::getBaseUrl() . 'index.php/index/index?site_id=' . urlencode($domainId));
    }

    public function apiBootstrapAction()
    {
        $this->api(function () {
            $domains = $this->domains->listDomains();
            $selected = $this->requestedDomainId();
            $locked = $selected !== null;
            $requestedDenied = false;
            if ($selected !== null) {
                $allowed = false;
                foreach ($domains as $domainRow) {
                    if ((int) $domainRow['id'] === (int) $selected) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    $requestedDenied = true;
                    $selected = null;
                    $domains = [];
                }
            }
            if ($selected === null) {
                $selected = $domains ? $domains[0]['id'] : null;
            }
            if ($locked && $selected !== null) {
                $domains = array_values(array_filter($domains, function ($domainRow) use ($selected) {
                    return (int) $domainRow['id'] === (int) $selected;
                }));
            }
            $runtime = null;
            if ($selected !== null) {
                $runtime = (new Modules_NodeManagerPm2_RuntimeService())->versions($this->domains->getDomain($selected));
            }
            $accessEnabled = !$requestedDenied && ($this->domains->isAdmin() || count($domains) > 0);
            $accessMessage = '';
            if ($requestedDenied) {
                $accessMessage = 'Node Manager (PM2) is not enabled for this domain. Ask the server administrator to enable Node Manager (PM2) access for this subscription and sync the subscription.';
            } elseif (!$accessEnabled) {
                $accessMessage = 'Node Manager (PM2) is not enabled for this account. Ask the server administrator to enable Node Manager (PM2) access for this subscription or service plan.';
            }

            return [
                'accessEnabled' => $accessEnabled,
                'accessMessage' => $accessMessage,
                'domains' => $domains,
                'selectedDomainId' => $selected,
                'domainLocked' => $locked,
                'settings' => $accessEnabled ? Modules_NodeManagerPm2_Config::all() : [],
                'runtime' => $runtime,
                'alerts' => $accessEnabled ? Modules_NodeManagerPm2_Store::instance()->unreadAlerts(20) : [],
                'permissions' => $this->permissionsFor($selected),
            ];
        }, false);
    }

    public function apiProcessesAction()
    {
        $this->api(function () {
            $domain = $this->domainFromRequest();
            return [
                'processes' => (new Modules_NodeManagerPm2_Pm2Service())->listProcesses($domain),
                'domain' => $this->domains->serialize($domain),
            ];
        }, false);
    }

    public function apiCreateProcessAction()
    {
        $this->api(function ($input) {
            $domain = $this->domainFromInput($input);
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::MANAGE, $domain->getId());
            Modules_NodeManagerPm2_PermissionService::assertCapacity(
                $domain->getId(),
                Modules_NodeManagerPm2_Store::instance()->countAppsByDomain($domain->getId())
            );
            $app = (new Modules_NodeManagerPm2_Pm2Service())->createProcess($domain, $input);
            return ['app' => $app];
        });
    }

    public function apiBrowseAction()
    {
        $this->api(function () {
            $domain = $this->domainFromRequest();
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::MANAGE, $domain->getId());
            $mode = $this->_getParam('mode', 'file') === 'directory' ? 'directory' : 'file';
            $path = $this->_getParam('path', '');
            return (new Modules_NodeManagerPm2_BrowseService())->browse($domain, $path, $mode);
        }, false);
    }

    public function apiFileAction()
    {
        $this->api(function ($input) {
            $domain = $this->domainFromInput($input);
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::MANAGE, $domain->getId());
            $path = isset($input['path']) ? $input['path'] : '';
            $service = new Modules_NodeManagerPm2_BrowseService();
            if (isset($input['content'])) {
                return $service->saveFile($domain, $path, $input['content']);
            }

            return $service->readFile($domain, $path);
        });
    }

    public function apiProcessAction()
    {
        $this->api(function ($input) {
            $domain = $this->domainFromInput($input);
            $action = isset($input['action']) ? $input['action'] : '';
            $pm2Name = isset($input['pm2Name']) ? $input['pm2Name'] : '';
            $service = new Modules_NodeManagerPm2_Pm2Service();

            if ($action === 'scale') {
                Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::CONTROL, $domain->getId());
                return $service->scale($domain, $pm2Name, isset($input['instances']) ? $input['instances'] : 1);
            }

            Modules_NodeManagerPm2_PermissionService::assertDomain(
                $action === 'delete' ? Modules_NodeManagerPm2_PermissionService::MANAGE : Modules_NodeManagerPm2_PermissionService::CONTROL,
                $domain->getId()
            );

            return $service->action($domain, $pm2Name, $action);
        });
    }

    public function apiMetricsAction()
    {
        $this->api(function () {
            $domain = $this->domainFromRequest();
            $pm2Name = Modules_NodeManagerPm2_Validator::processName($this->_getParam('pm2Name', ''));
            return [
                'metrics' => Modules_NodeManagerPm2_Store::instance()->metrics($domain->getId(), $pm2Name, 60),
            ];
        }, false);
    }

    public function apiLogsAction()
    {
        $this->api(function () {
            $domain = $this->domainFromRequest();
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::LOGS, $domain->getId());
            $pm2Name = $this->_getParam('pm2Name', '');
            $stream = $this->_getParam('stream', 'stdout');
            $bytes = (int) $this->_getParam('bytes', Modules_NodeManagerPm2_Config::get('maxLogBytes', 200000));
            return (new Modules_NodeManagerPm2_LogService())->read($domain, $pm2Name, $stream, $bytes);
        }, false);
    }

    public function apiClearLogsAction()
    {
        $this->api(function ($input) {
            $domain = $this->domainFromInput($input);
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::MANAGE, $domain->getId());
            (new Modules_NodeManagerPm2_LogService())->clear(
                $domain,
                isset($input['pm2Name']) ? $input['pm2Name'] : '',
                isset($input['stream']) ? $input['stream'] : 'all'
            );
            return ['cleared' => true];
        });
    }

    public function downloadLogsAction()
    {
        try {
            $domain = $this->domainFromRequest();
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::LOGS, $domain->getId());
            $pm2Name = $this->_getParam('pm2Name', '');
            $stream = $this->_getParam('stream', 'stdout');
            $file = (new Modules_NodeManagerPm2_LogService())->download($domain, $pm2Name, $stream);

            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        } catch (Exception $e) {
            Modules_NodeManagerPm2_Response::error($this, $e->getMessage(), 400);
        }
    }

    public function apiEnvAction()
    {
        $this->api(function ($input) {
            $domain = $this->domainFromInput($input);
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::MANAGE, $domain->getId());
            $service = new Modules_NodeManagerPm2_EnvService();
            $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
            $appId = isset($input['appId']) ? $input['appId'] : $this->_getParam('appId', '');

            if ($method === 'GET') {
                return ['env' => $service->listForApp($domain, $appId)];
            }

            if (!empty($input['delete'])) {
                $service->delete($domain, $appId, isset($input['name']) ? $input['name'] : '');
            } else {
                $service->save($domain, $appId, $input);
            }

            return ['env' => $service->listForApp($domain, $appId)];
        });
    }

    public function apiEcosystemAction()
    {
        $this->api(function ($input) {
            $domain = $this->domainFromInput($input);
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::MANAGE, $domain->getId());
            $pm2 = new Modules_NodeManagerPm2_Pm2Service();
            $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
            $appId = isset($input['appId']) ? $input['appId'] : $this->_getParam('appId', '');

            if ($method === 'GET') {
                return $pm2->readEcosystem($domain, $appId);
            }

            $content = isset($input['content']) ? $input['content'] : '';
            if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                $content = file_get_contents($_FILES['file']['tmp_name']);
            }

            $result = $pm2->writeEcosystem($domain, $appId, $content);
            if (!empty($input['start'])) {
                $pm2->startEcosystem($domain, $appId);
            }
            return $result;
        });
    }

    public function apiDeployAction()
    {
        $this->api(function ($input) {
            $domain = $this->domainFromInput($input);
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::MANAGE, $domain->getId());
            $appId = isset($input['appId']) ? $input['appId'] : '';
            return (new Modules_NodeManagerPm2_DeployService())->deploy($domain, $appId, [
                'npmInstall' => !empty($input['npmInstall']),
                'reload' => !empty($input['reload']),
                'production' => !empty($input['production']),
            ]);
        });
    }

    public function apiWebhookAction()
    {
        $this->api(function ($input) {
            $domain = $this->domainFromInput($input);
            Modules_NodeManagerPm2_PermissionService::assertDomain(Modules_NodeManagerPm2_PermissionService::MANAGE, $domain->getId());
            return (new Modules_NodeManagerPm2_DeployService())->configureWebhook(
                $domain,
                isset($input['appId']) ? $input['appId'] : '',
                !empty($input['enabled'])
            );
        });
    }

    public function apiRuntimeAction()
    {
        $this->api(function ($input) {
            $domain = $this->domainFromInput($input);
            $runtime = new Modules_NodeManagerPm2_RuntimeService();
            if (!empty($input['applyDetectedPaths'])) {
                if (!$this->domains->isAdmin()) {
                    throw new Modules_NodeManagerPm2_Exception('Only administrators can update runtime paths.');
                }
                $settings = $runtime->applyDetectedPaths();
                return ['settings' => $settings, 'runtime' => $runtime->versions($domain)];
            }
            if (!empty($input['installPm2'])) {
                if (!$this->domains->isAdmin()) {
                    throw new Modules_NodeManagerPm2_Exception('Only administrators can install or update PM2.');
                }
                Modules_NodeManagerPm2_Logger::info('PM2 install requested.', [
                    'domainId' => $domain->getId(),
                    'domainName' => $domain->getName(),
                    'requestUri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
                ]);
                return ['output' => $runtime->installOrUpdatePm2(), 'runtime' => $runtime->versions($domain)];
            }
            return ['runtime' => $runtime->versions($domain)];
        });
    }

    public function apiSettingsAction()
    {
        $this->api(function ($input) {
            if (!$this->domains->isAdmin()) {
                throw new Modules_NodeManagerPm2_Exception('Only administrators can update extension settings.');
            }

            foreach (Modules_NodeManagerPm2_Config::defaults() as $key => $value) {
                if (isset($input[$key])) {
                    Modules_NodeManagerPm2_Config::set($key, $input[$key]);
                }
            }

            return ['settings' => Modules_NodeManagerPm2_Config::all()];
        });
    }

    public function apiBackupAction()
    {
        $this->api(function ($input) {
            if (!$this->domains->isAdmin()) {
                throw new Modules_NodeManagerPm2_Exception('Only administrators can manage backups.');
            }
            $service = new Modules_NodeManagerPm2_BackupService();
            if (!empty($input['create'])) {
                return $service->create();
            }
            if (!empty($input['restore'])) {
                return $service->restore($input['restore']);
            }
            return ['backups' => $service->listBackups()];
        });
    }

    private function api($callback, $csrf = true)
    {
        try {
            $input = $this->input();
            if ($csrf) {
                Modules_NodeManagerPm2_Security::assertCsrf();
            }
            Modules_NodeManagerPm2_Response::ok($this, $callback($input));
        } catch (Modules_NodeManagerPm2_Exception $e) {
            Modules_NodeManagerPm2_Logger::warning('API request failed.', $this->errorContext($e));
            Modules_NodeManagerPm2_Response::error($this, $e->getMessage(), 400);
        } catch (Exception $e) {
            Modules_NodeManagerPm2_Logger::error('API request failed unexpectedly.', $this->errorContext($e));
            Modules_NodeManagerPm2_Response::error($this, 'Unexpected error: ' . $e->getMessage(), 500);
        } catch (Throwable $e) {
            Modules_NodeManagerPm2_Logger::error('API request failed fatally.', $this->throwableContext($e));
            Modules_NodeManagerPm2_Response::error($this, 'Unexpected error: ' . $e->getMessage(), 500);
        }
    }

    private function input()
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }

        return $_POST;
    }

    private function domainFromRequest()
    {
        $domainId = (int) $this->_getParam('domainId', 0);
        if ($domainId <= 0) {
            $domainId = (int) $this->requestedDomainId();
        }
        if ($domainId <= 0) {
            $domains = $this->domains->listDomains();
            if (!$domains) {
                throw new Modules_NodeManagerPm2_Exception('No hosted domains are available.');
            }
            $domainId = $domains[0]['id'];
        }

        return $this->domains->getDomain($domainId);
    }

    private function domainFromInput($input)
    {
        $domainId = isset($input['domainId']) ? (int) $input['domainId'] : (int) $this->_getParam('domainId', 0);
        if ($domainId <= 0) {
            throw new Modules_NodeManagerPm2_Exception('Domain is required.');
        }

        return $this->domains->getDomain($domainId);
    }

    private function endpoints()
    {
        return [
            'bootstrap' => pm_Context::getActionUrl('index', 'api-bootstrap'),
            'processes' => pm_Context::getActionUrl('index', 'api-processes'),
            'createProcess' => pm_Context::getActionUrl('index', 'api-create-process'),
            'browse' => pm_Context::getActionUrl('index', 'api-browse'),
            'file' => pm_Context::getActionUrl('index', 'api-file'),
            'process' => pm_Context::getActionUrl('index', 'api-process'),
            'metrics' => pm_Context::getActionUrl('index', 'api-metrics'),
            'logs' => pm_Context::getActionUrl('index', 'api-logs'),
            'clearLogs' => pm_Context::getActionUrl('index', 'api-clear-logs'),
            'downloadLogs' => pm_Context::getActionUrl('index', 'download-logs'),
            'env' => pm_Context::getActionUrl('index', 'api-env'),
            'ecosystem' => pm_Context::getActionUrl('index', 'api-ecosystem'),
            'deploy' => pm_Context::getActionUrl('index', 'api-deploy'),
            'webhook' => pm_Context::getActionUrl('index', 'api-webhook'),
            'runtime' => pm_Context::getActionUrl('index', 'api-runtime'),
            'settings' => pm_Context::getActionUrl('index', 'api-settings'),
            'backup' => pm_Context::getActionUrl('index', 'api-backup'),
        ];
    }

    private function permissionsFor($domainId)
    {
        $admin = $this->domains->isAdmin();
        return [
            'admin' => $admin,
            'canInstallPm2' => $admin,
            'canManage' => $admin || Modules_NodeManagerPm2_PermissionService::can(Modules_NodeManagerPm2_PermissionService::MANAGE, $domainId),
            'canControl' => $admin || Modules_NodeManagerPm2_PermissionService::can(Modules_NodeManagerPm2_PermissionService::CONTROL, $domainId),
            'canLogs' => $admin || Modules_NodeManagerPm2_PermissionService::can(Modules_NodeManagerPm2_PermissionService::LOGS, $domainId),
        ];
    }

    private function requestedDomainId()
    {
        $siteId = $this->requestValue('site_id');
        if ($siteId !== null) {
            return $this->normalizeDomainId($siteId);
        }

        foreach (['domainId', 'domain_id', 'contextDomainId', 'context_domain_id'] as $name) {
            $value = $this->requestValue($name);
            if ($value !== null) {
                return $this->normalizeDomainId($value);
            }
        }

        $domId = $this->requestValue('dom_id');
        if ($domId !== null) {
            return $this->resolveSubscriptionDomainId($domId);
        }

        return null;
    }

    private function requestValue($name)
    {
        $value = $this->_request->getParam($name);
        return $value !== null && $value !== '' ? $value : null;
    }

    private function normalizeDomainId($domainId)
    {
        try {
            $domain = pm_Domain::getByDomainId((int) $domainId);
            return (int) $domain->getId();
        } catch (Exception $e) {
            try {
                $domain = new pm_Domain((int) $domainId);
                return (int) $domain->getId();
            } catch (Exception $inner) {
                return null;
            }
        }
    }

    private function resolveSubscriptionDomainId($subscriptionId)
    {
        $directDomainId = $this->normalizeDomainId($subscriptionId);
        if ($directDomainId !== null) {
            return $directDomainId;
        }

        try {
            foreach (pm_Domain::getAllDomains(false) as $domain) {
                if (!$domain->hasHosting()) {
                    continue;
                }
                foreach (['webspace_id', 'parentDomainId'] as $property) {
                    try {
                        if ((int) $domain->getProperty($property) === (int) $subscriptionId) {
                            return (int) $domain->getId();
                        }
                    } catch (Exception $e) {
                    }
                }
            }
        } catch (Exception $e) {
        }

        return null;
    }

    private function assetVersion()
    {
        if (class_exists('pm_Context') && method_exists('pm_Context', 'getMetaData')) {
            try {
                $meta = pm_Context::getMetaData();
                if (is_array($meta) && !empty($meta['version'])) {
                    return $meta['version'];
                }
            } catch (Exception $e) {
            }
        }

        return '1.0.0';
    }

    private function extensionInfo()
    {
        $version = $this->assetVersion();
        return [
            'name' => 'Node Manager (PM2)',
            'version' => $version,
            'creator' => 'Ghost Compiler',
            'email' => 'hello@ghostcompiler.in',
            'profileUrl' => 'https://github.com/ghostcompiler',
            'github' => 'node-manager-pm2',
            'githubUrl' => 'https://github.com/ghostcompiler/node-manager-pm2',
            'logPath' => '/usr/local/psa/var/modules/node-manager-pm2/logs/node-manager-pm2.log',
            'icon' => pm_Context::getBaseUrl() . 'images/icon.png',
        ];
    }

    private function indexUrl($domainId)
    {
        return pm_Context::getActionUrl('index', 'index') . $this->domainQuery($domainId);
    }

    private function infoUrl($domainId)
    {
        return pm_Context::getActionUrl('index', 'info') . $this->domainQuery($domainId);
    }

    private function domainQuery($domainId)
    {
        if ($domainId === null || $domainId === '') {
            return '';
        }

        return '?site_id=' . urlencode($domainId);
    }

    private function errorContext(Exception $e)
    {
        return [
            'requestUri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
            'exception' => $e,
        ];
    }

    private function throwableContext($e)
    {
        return [
            'requestUri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
            'exception' => [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => method_exists($e, 'getTraceAsString') ? $e->getTraceAsString() : '',
            ],
        ];
    }
}
