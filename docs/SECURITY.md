# Security Model

## Tenant Isolation

- All domain-scoped process commands are executed through
  `pm_ApiCli::callDomain()` when running inside Plesk.
- Commands run with `PM2_HOME=<domain-home>/.pm2`.
- Domain access is checked through the current Plesk session client.
- Non-admin users see only domains where the subscription has the Node Manager
  PM2 access permission.
- Paths are normalized and must remain inside the selected subscription home.

## Plesk Permissions

The extension registers service-plan permissions for:

- Node Manager (PM2) access
- Control PM2 processes
- View PM2 logs
- Manage PM2 applications

It also registers a per-subscription PM2 application limit. A non-admin user
must have access enabled before any more specific permission is honored.

## Command Execution

- Commands and arguments are passed as arrays to the Plesk CLI bridge.
- The local development fallback shell-escapes every argument.
- User-provided process names, environment names, branch names, and paths are
  validated before execution.
- Privileged PM2 installation is isolated to `sbin/pm2-helper` and available
  only to Plesk administrators.

## Secrets

- Environment values are encrypted before being stored.
- Inside Plesk, encryption uses `pm_Crypt`.
- Outside Plesk, a local AES key is created under the extension `var` directory.
- Secret values are not returned to the UI after save.

## Webhooks

- Deployment webhooks use per-app random tokens.
- Only the SHA-256 token hash is stored.
- The public endpoint accepts only an app id and token, then performs the same
  app/domain lookup used by authenticated deploys.

## Operational Hardening

- Restrict extension access to trusted hosting roles.
- Keep PM2, Node.js, npm, and Git patched.
- Prefer SSH deploy keys owned by the domain system user.
- Monitor the Plesk action log and web server logs for failed webhook attempts.
