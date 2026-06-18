# Changelog

## 1.0.19-1

- Added persistent download statistics tracking for the latest release.
- Added dynamic Shields.io downloads badge to the README.

## 1.0.18-1

- Replaced fragile Plesk `Grid`, `Item`, `Tabs`, and toolbar layouts on the
  main page with fixed extension-owned responsive layout classes.
- Fixed the domain summary section so subscription user, application root, and
  permissions no longer wrap into unreadable vertical text in dark mode.
- Restyled top actions, tabs, setup notices, stats, runtime/settings/backups
  panels, empty states, and create/info dialogs to avoid overflow and cache
  stale `1.0.17` UI assets.

## 1.0.17-1

- Rebuilt the frontend with the Plesk React UI Library pattern used by the
  Cloudflare DNS extension.
- Replaced custom Vue screens with Plesk `List`, `Tabs`, `Dialog`, `Toolbar`,
  `Status`, `Switch`, `Pagination`, and `CodeEditor` components.
- Moved process details, create process, info, and file browsing into official
  Plesk dialogs and removed the legacy custom drawer.
- Added npm/webpack build steps and generated the new AMD UI bundle under
  `htdocs/dist`.

## 1.0.16-1

- Fixed process-table panel sizing so the empty-state row and process actions
  stay inside the Plesk content area without page-level overflow.

## 1.0.15-1

- Replaced the custom extension artwork with a PM2-branded purple icon for
  Plesk navigation, UI metadata, and extension catalog assets.

## 1.0.14-1

- Added GitHub Actions runners for CI packaging, versioned releases, and a
  rolling latest package download.
- Reworked installation documentation to match the stable install-url flow used
  by the Supervisor extension.
- Documented local build, test, upload, and latest-package installation paths.

## 1.0.13-1

- Hide customer and reseller navigation buttons when Node Manager (PM2) access
  is not enabled for the account.
- Show a dedicated not-enabled state instead of runtime diagnostics when a user
  opens the extension without an enabled domain.
- Require the Node Manager access permission before service-plan limits can
  grant process management.

## 1.0.12-1

- Moved the process detail surface from the bottom drawer to a top-positioned
  modal so logs, env, deploy, ecosystem, and metrics are easier to work with.
- Fixed checkbox sizing in Env and Deploy tabs and stopped action buttons from
  stretching across the whole drawer.
- Reduced ecosystem editor height so the modal fits better in the Plesk
  viewport.

## 1.0.11-1

- Locked dashboard bootstrap to the Plesk domain context when opened from a
  domain card, matching the Supervisor extension pattern.
- Filtered PM2 process rows by the selected domain application root so shared
  subscription users do not show sibling-domain processes.
- Scoped script and working-directory selection to the selected domain
  application root for safer process creation.
- Replaced the placeholder metrics strip with an SVG CPU/memory graph and added
  pagination to the metric history table.
- Tightened process-table wrapping and made Env, Deploy, and Ecosystem tabs
  return actionable feedback when a process is not managed for that domain.

## 1.0.10-1

- Added Plesk permission and subscription limit hooks for reseller/customer
  access, control, logs, and PM2 app management.
- Filtered domains and enforced backend permissions for create, control, logs,
  deployment, environment, ecosystem, file browsing, and webhook actions.
- Separated the domain selector into its own Plesk-style domain card and made
  process actions permission-aware.
- Reworked the process drawer positioning so it no longer opens underneath the
  Plesk sidebar.
- Replaced the old dark module icon with a brighter PM2 icon for navigation,
  catalog, and extension cards.
- Rebuilt the autorestart control as a compact toggle so it is easy to click
  and does not stretch across the form.
- Added an inline extension information card with install and log-file details.

## 1.0.9-1

- Replaced the process detail side panel with a bottom drawer so logs, env,
  deploy, ecosystem, and metrics stay in the Plesk content flow.
- Made the process table full width and cleared stale action errors after
  successful process refreshes and detail tab changes.
- Added a domain-scoped file and directory picker for Script path and Working
  directory fields, with separate Open and Select actions.
- Polished the create form checkbox sizing and path hints for subdomain
  document-root workflows.

## 1.0.8-1

- Resolve relative process script and working-directory paths from the selected
  domain document root, with a fallback to the subscription home for existing
  paths.
- Display the selected domain document root in the command bar and create form
  so subdomain apps are not confused with the subscription root.

## 1.0.7-1

- Execute PM2 through the detected Node.js binary, for example
  `node /opt/plesk/node/24/bin/pm2`, so Plesk subscription execution does not
  depend on shell PATH resolution for npm-installed launchers.
- Applied the same PM2 invocation path to runtime checks, process listing,
  process actions, scaling, saves, logs, and ecosystem starts.

## 1.0.6-1

- Added Plesk's `forgery_protection_token` to all AJAX POST requests using the
  standard meta token and `X-Forgery-Protection-Token` header.
- Fixed Plesk framework-level `Session token has expired` failures before API
  requests reached the extension controller.

## 1.0.5-1

- Added extension-local diagnostics at
  `/usr/local/psa/var/modules/node-manager-pm2/logs/node-manager-pm2.log`.
- Logged PM2 install requests, helper output, and API failures for easier
  troubleshooting from the server.

## 1.0.4-1

- Fixed PM2 install/update calls through the Plesk `sbin` bridge and returned
  actionable JSON errors when installation cannot complete.
- Hid Processes, Settings, Backups, and New Process until PM2 is installed and
  the runtime is ready.
- Improved runtime refresh behavior and backend result parsing for Plesk CLI
  responses.

## 1.0.3-1

- Reworked the UI to match Plesk extension styling instead of a dark standalone
  dashboard.
- Added runtime setup guidance, detected binary paths, and admin-only PM2
  installation controls.
- Moved navigation/custom buttons to Plesk-appropriate locations, including
  domain Dev Tools context buttons.

## 1.0.2-1

- Load frontend assets through Plesk/Zend `headLink()` and `headScript()`
  instead of raw view tags.
- Added versioned asset URLs and DOM-ready mounting to prevent stale JS cache
  and head-script timing issues.

## 1.0.1-1

- Fixed Plesk UI bootstrap hang by replacing the Axios dependency with native
  `fetch()` and adding a visible startup error panel for missing assets or
  non-JSON backend responses.

## 1.0.0-1

- Initial production scaffold for Node Manager (PM2).
- Added Plesk extension metadata, controller, hooks, scripts, backend services,
  Vue UI, webhook endpoint, deployment tools, runtime checks, docs, and package
  manifest.
