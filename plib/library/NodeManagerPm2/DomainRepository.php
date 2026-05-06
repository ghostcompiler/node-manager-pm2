<?php
class Modules_NodeManagerPm2_DomainRepository
{
    public function currentClient()
    {
        if (!class_exists('pm_Session')) {
            return null;
        }

        try {
            return Modules_NodeManagerPm2_PermissionService::currentClient();
        } catch (Exception $e) {
            return null;
        }
    }

    public function listDomains()
    {
        if (!class_exists('pm_Domain')) {
            return [];
        }

        $domains = Modules_NodeManagerPm2_PermissionService::accessibleDomains(Modules_NodeManagerPm2_PermissionService::ACCESS);

        $items = [];
        foreach ($domains as $domain) {
            try {
                if (!$domain->hasHosting()) {
                    continue;
                }
                $items[] = $this->serialize($domain);
            } catch (Exception $e) {
                continue;
            }
        }

        usort($items, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }

    public function getDomain($domainId)
    {
        if (!class_exists('pm_Domain')) {
            throw new Modules_NodeManagerPm2_Exception('Plesk domain API is not available outside Plesk.');
        }

        $domain = pm_Domain::getByDomainId((int) $domainId);
        $this->assertAccess($domain);

        if (!$domain->hasHosting()) {
            throw new Modules_NodeManagerPm2_Exception('The selected domain does not have physical hosting.');
        }

        return $domain;
    }

    public function assertAccess($domain)
    {
        if (Modules_NodeManagerPm2_PermissionService::isAdmin()) {
            return;
        }

        if (Modules_NodeManagerPm2_PermissionService::can(Modules_NodeManagerPm2_PermissionService::ACCESS, $domain->getId())) {
            return;
        }

        throw new Modules_NodeManagerPm2_Exception('Node Manager (PM2) is not enabled for this subscription.');
    }

    public function serialize($domain)
    {
        return [
            'id' => (int) $domain->getId(),
            'name' => $domain->getDisplayName(),
            'asciiName' => $domain->getName(),
            'homePath' => $domain->getHomePath(),
            'documentRoot' => Modules_NodeManagerPm2_Validator::defaultWorkingRoot($domain),
            'systemUser' => $domain->getSysUserLogin(),
            'active' => $domain->isActive(),
            'permissions' => Modules_NodeManagerPm2_PermissionService::domainPermissions($domain->getId()),
        ];
    }

    public function isAdmin()
    {
        return Modules_NodeManagerPm2_PermissionService::isAdmin();
    }
}
