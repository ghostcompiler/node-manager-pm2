# JSON API

Authenticated APIs are served by `IndexController` and require the Plesk session.
Mutating calls require the `X-Node-Manager-CSRF` header emitted in the page
configuration.

Domain APIs also honor Plesk service-plan permissions. Non-admin users need
Node Manager access plus the matching control, logs, or management permission.

## Endpoints

- `api-bootstrap`: domains, settings, runtime summary, permissions, alerts.
- `api-processes`: PM2 process list for a domain.
- `api-create-process`: create and start a PM2 process.
- `api-process`: start, stop, restart, reload, delete, or scale a process.
- `api-metrics`: recent metrics for a process.
- `api-logs`: tail stdout or stderr logs.
- `api-clear-logs`: truncate stdout, stderr, or both logs.
- `download-logs`: authenticated log file download.
- `api-env`: list, save, or delete app environment variables.
- `api-ecosystem`: read, save, upload, and optionally start ecosystem config.
- `api-deploy`: git pull, optional npm install, and process restart/reload.
- `api-webhook`: enable or disable a deploy webhook token.
- `api-runtime`: runtime versions and admin PM2 install/update action.
- `api-browse`: domain-scoped file and directory picker data for process forms.
- `api-settings`: admin binary path and retention settings.
- `api-backup`: admin metadata backup creation and listing.

## Public Webhook

`htdocs/public/webhook.php?app=<app-id>&token=<token>`

The endpoint triggers the production deployment profile:

- Git update
- `npm install --omit=dev`
- zero-downtime reload

Tokens are generated from the authenticated UI and only their hashes are stored.
