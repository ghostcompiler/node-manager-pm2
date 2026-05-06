# Architecture

## Backend

- `IndexController`: Plesk MVC controller and JSON API boundary.
- `DomainRepository`: current-user domain visibility and serialization.
- `PermissionService`: Plesk service-plan and reseller/customer permission
  checks, including per-subscription process limits.
- `CommandRunner`: secure command bridge with `pm_ApiCli::callDomain()` support.
- `Pm2Service`: PM2 process orchestration, `jlist` parsing, scaling, reloads,
  ecosystem config management, and metric capture.
- `LogService`: safe stdout/stderr tailing, clearing, and download.
- `EnvService`: encrypted env var persistence and ecosystem synchronization.
- `DeployService`: Git update, npm install, restart/reload, and webhook tokens.
- `RuntimeService`: Node, npm, Git, and PM2 version detection.
- `BackupService`: metadata export to JSON.
- `Store`: SQLite persistence in the Plesk module `var` directory.

## Frontend

- React app in `frontend/index.js`, bundled with `@plesk/plesk-ext-sdk`.
- Webpack emits an AMD bundle to `htdocs/dist/node-manager-pm2-ui.js` and
  Plesk loads it through RequireJS.
- The UI uses Plesk UI Library components such as `List`, `Tabs`, `Dialog`,
  `Toolbar`, `Status`, `Switch`, `Pagination`, and `CodeEditor`.
- API calls use browser-native `fetch()` and include Plesk's
  `forgery_protection_token` for POST requests.
- A small stylesheet in `htdocs/css/app.css` only covers PM2-specific layout,
  log terminal, and metric graph polish.
- Polling is used for process and log updates to avoid long-running web workers
  inside Plesk's admin panel.

## PM2 Isolation

Each domain runs with:

```text
HOME=<domain-home>
PM2_HOME=<domain-home>/.pm2
USER=<domain-system-user>
```

This keeps PM2 process lists, logs, dumps, and runtime state scoped to the
subscription user.
