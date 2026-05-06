<?php
class Modules_NodeManagerPm2_PermissionService
{
    const ACCESS = 'access_node_manager_pm2';
    const MANAGE = 'manage_node_pm2_processes';
    const CONTROL = 'control_node_pm2_processes';
    const LOGS = 'view_node_pm2_logs';
    const LIMIT_APPS = 'max_node_pm2_apps';

    public static function currentClient()
    {
        try {
            if (
                method_exists('pm_Session', 'isImpersonated') &&
                method_exists('pm_Session', 'getImpersonatedClientId') &&
                pm_Session::isImpersonated()
            ) {
                return pm_Client::getByClientId(pm_Session::getImpersonatedClientId());
            }
        } catch (Exception $e) {
        }

        return pm_Session::getClient();
    }

    public static function isAdmin()
    {
        $client = self::currentClient();
        return $client && method_exists($client, 'isAdmin') && $client->isAdmin();
    }

    public static function can($permission, $domainId)
    {
        $client = self::currentClient();
        if ($client && method_exists($client, 'isAdmin') && $client->isAdmin()) {
            return true;
        }

        if ($domainId === null || $domainId === '') {
            return false;
        }

        try {
            $domain = new pm_Domain((int) $domainId);
            if (!self::hasDomainAccess($client, $domain)) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        if (!self::hasEffectivePermission($client, $domain, self::ACCESS)) {
            return false;
        }

        if ($permission === self::ACCESS) {
            return true;
        }

        return self::hasEffectivePermission($client, $domain, $permission);
    }

    public static function canAny($permission)
    {
        if (self::isAdmin()) {
            return true;
        }

        foreach (self::accessibleDomains() as $domain) {
            try {
                if ($domain->hasHosting() && self::can($permission, $domain->getId())) {
                    return true;
                }
            } catch (Exception $e) {
            }
        }

        return false;
    }

    public static function accessibleDomains($permission = self::ACCESS)
    {
        if (!class_exists('pm_Domain')) {
            return [];
        }

        if (self::isAdmin()) {
            return pm_Domain::getAllDomains(false);
        }

        $client = self::currentClient();
        $domains = [];

        try {
            foreach (pm_Session::getCurrentDomains() as $domain) {
                if (self::hasDomainAccess($client, $domain) && self::can($permission, $domain->getId())) {
                    $domains[$domain->getId()] = $domain;
                }
            }
        } catch (Exception $e) {
        }

        try {
            $domain = pm_Session::getCurrentDomain();
            if (self::hasDomainAccess($client, $domain) && self::can($permission, $domain->getId())) {
                $domains[$domain->getId()] = $domain;
            }
        } catch (Exception $e) {
        }

        try {
            foreach (pm_Domain::getDomainsByClient($client, false) as $domain) {
                if (self::hasDomainAccess($client, $domain) && self::can($permission, $domain->getId())) {
                    $domains[$domain->getId()] = $domain;
                }
            }
        } catch (Exception $e) {
        }

        try {
            foreach (pm_Domain::getAllDomains(false) as $domain) {
                try {
                    if (self::hasDomainAccess($client, $domain) && self::can($permission, $domain->getId())) {
                        $domains[$domain->getId()] = $domain;
                    }
                } catch (Exception $e) {
                }
            }
        } catch (Exception $e) {
        }

        return $domains;
    }

    public static function assertDomain($permission, $domainId)
    {
        if (!self::can($permission, $domainId)) {
            throw new Modules_NodeManagerPm2_Exception('Node Manager (PM2) is not enabled for this domain or action.');
        }
    }

    public static function assertCapacity($domainId, $currentCount)
    {
        if (self::isAdmin()) {
            return;
        }

        if (!self::hasCapacity($domainId, $currentCount)) {
            throw new Modules_NodeManagerPm2_Exception('Node Manager (PM2) process limit reached for this domain.');
        }
    }

    public static function hasCapacity($domainId, $currentCount)
    {
        if (self::isAdmin()) {
            return true;
        }

        try {
            $domain = new pm_Domain((int) $domainId);
            $limit = self::normalizeLimit($domain->getLimit(self::LIMIT_APPS));
        } catch (Exception $e) {
            return false;
        }

        return $limit < 0 || (int) $currentCount < $limit;
    }

    public static function domainPermissions($domainId)
    {
        return [
            'access' => self::can(self::ACCESS, $domainId),
            'manage' => self::can(self::MANAGE, $domainId),
            'control' => self::can(self::CONTROL, $domainId),
            'logs' => self::can(self::LOGS, $domainId),
        ];
    }

    public static function hasDomainAccess($client, $domain)
    {
        if (!$client) {
            return false;
        }

        if (method_exists($client, 'isAdmin') && $client->isAdmin()) {
            return true;
        }

        try {
            if (method_exists($client, 'hasAccessToDomain') && $client->hasAccessToDomain($domain->getId())) {
                return true;
            }
        } catch (Exception $e) {
        }

        try {
            $owner = $domain->getClient();
            if ((int) $owner->getId() === (int) $client->getId()) {
                return true;
            }

            if (method_exists($client, 'isReseller') && $client->isReseller()) {
                try {
                    if ((int) $owner->getProperty('vendor_id') === (int) $client->getId()) {
                        return true;
                    }
                } catch (Exception $e) {
                }
            }
        } catch (Exception $e) {
        }

        return false;
    }

    private static function hasEffectivePermission($client, $domain, $permission)
    {
        if (self::hasClientPermission($client, $permission, $domain) || self::hasPlanPermission($domain, $permission)) {
            return true;
        }

        return self::isLimitPermissionFallback($domain, $permission);
    }

    private static function hasPlanPermission($domain, $permission)
    {
        try {
            return self::normalizeBoolean($domain->hasPermission($permission));
        } catch (Exception $e) {
            return false;
        }
    }

    private static function hasClientPermission($client, $permission, $domain)
    {
        if ($client === null || $domain === null) {
            return false;
        }

        try {
            return self::normalizeBoolean($client->hasPermission($permission, $domain));
        } catch (Exception $e) {
            return false;
        }
    }

    private static function isLimitPermissionFallback($domain, $permission)
    {
        if ($permission !== self::MANAGE) {
            return false;
        }

        if (
            !self::hasClientPermission(self::currentClient(), self::ACCESS, $domain) &&
            !self::hasPlanPermission($domain, self::ACCESS)
        ) {
            return false;
        }

        try {
            return self::normalizeLimit($domain->getLimit(self::LIMIT_APPS)) !== 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function normalizeLimit($value)
    {
        if (is_int($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        if ($value === '' || $value === 'false') {
            return 0;
        }
        if ($value === 'unlimited' || $value === '-1') {
            return -1;
        }

        return (int) $value;
    }

    private static function normalizeBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'on', 'yes', 'enabled'], true);
    }
}
