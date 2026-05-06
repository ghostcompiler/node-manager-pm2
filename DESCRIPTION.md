# Node Manager (PM2)

Node Manager (PM2) brings production Node.js process operations into Plesk.
It gives administrators, resellers, and customers a UI for PM2-powered apps
without requiring SSH access.

## Highlights

- PM2 process list, start, stop, restart, reload, delete, and create actions.
- Cluster mode and instance scaling with zero-downtime reload support.
- Live-ish metrics for CPU, memory, uptime, restart count, and PM2 status.
- Log viewer for stdout and stderr with filtering, download, and clear actions.
- Environment variable management with encrypted-at-rest values.
- Ecosystem config editor and upload workflow.
- Git deployment workflow with pull, optional `npm install`, and restart/reload.
- Per-domain PM2 home directories and subscription-user execution.
- Runtime view for Node, npm, Git, and PM2 versions.
- Webhook deploy tokens for automation.
- Basic alerts, metrics retention, and backup/restore of PM2 metadata.

## Security Model

The extension executes domain-scoped commands through Plesk's domain user bridge
when available. Every command is built from argument arrays, paths are validated
against the selected domain home, process operations are constrained to the
selected subscription context, and environment values are encrypted before being
stored.

## Requirements

- Plesk Obsidian 18.0.34 or newer.
- Linux hosting with physical hosting enabled for managed domains.
- Node.js, npm, Git, and PM2 installed either globally or in paths configured in
  the extension settings.

The extension includes an admin action and privileged helper for installing or
updating PM2 globally when permitted by the server policy.
