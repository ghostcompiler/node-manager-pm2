# Installation

## Requirements

- Plesk Obsidian 18.0.34 or newer on Linux.
- Physical hosting enabled for domains that will run Node.js apps.
- Plesk Node.js extension or another Node.js/npm runtime available on the server.
- SQLite PDO extension available to the Plesk PHP runtime.
- Git installed for deployment features.
- PM2 installed globally or installed from the extension runtime page by an administrator.

## Install Latest

Install the latest runner-built package directly from GitHub:

```sh
plesk bin extension --install-url https://github.com/ghostcompiler/node-manager-pm2/releases/download/latest/node-manager-pm2.zip
```

The `latest` release is maintained by `.github/workflows/package-latest.yml`.
It rebuilds `node-manager-pm2.zip` from `main` on every push and when the
workflow is started manually.

## Install Pinned Version

After publishing a versioned release:

```sh
plesk bin extension --install-url https://github.com/ghostcompiler/node-manager-pm2/releases/download/v1.0.0/node-manager-pm2-1.0.0.zip
```

## Build Package Locally

Run from the extension root:

```sh
./packaging/build.sh
```

The script creates:

```text
node-manager-pm2-1.0.0.zip
```

## Install Local Package

Install through Plesk CLI:

```sh
plesk bin extension --install node-manager-pm2-1.0.0.zip
```

Or install through Plesk UI:

1. Open **Plesk Admin**.
2. Go to **Extensions**.
3. Click **Upload Extension**.
4. Upload `node-manager-pm2-1.0.0.zip`.
5. Open **Node Manager (PM2)** from the Plesk sidebar or a domain page.

The extension initializes its SQLite database in the module `var` directory and
seeds default settings for binary paths, polling, logs, and metrics retention.

## Test Before Packaging

Run the same checks used by the GitHub Actions runners:

```sh
npm ci --ignore-scripts --legacy-peer-deps
npm run build
find plib htdocs -type f \( -name '*.php' -o -name '*.phtml' \) -print0 | xargs -0 -n1 php -l
xmllint --noout meta.xml
node -e "JSON.parse(require('fs').readFileSync('packaging/manifest.json', 'utf8'))"
sh -n packaging/build.sh
sh -n sbin/pm2-helper
./packaging/build.sh
zip -T node-manager-pm2-1.0.0.zip
```

## GitHub Runners

- **CI**: validates PHP, JavaScript, metadata, shell scripts, builds the package,
  and uploads package artifacts.
- **Package Latest**: publishes `node-manager-pm2.zip` to the rolling `latest`
  release.
- **Release**: publishes `node-manager-pm2-<version>.zip` for `v<version>` tags.

## Upgrade

Build a new ZIP with the same structure and install it through Plesk. The `var`
directory is preserved by Plesk during extension upgrades.

## First Run

1. Open the affected service plan or subscription.
2. Enable **Node Manager (PM2) access**.
3. Enable the needed action permissions: control, logs, and manage.
4. Set **Maximum PM2 applications** to a non-zero value for subscriptions that can create processes.
5. Sync existing subscriptions if Plesk marks them as customized or out of sync.
6. Open **Node Manager (PM2)** as admin, reseller, or customer.
7. Review the Runtime tab and install PM2 if needed.

## Troubleshooting Logs

When the UI reports a 500 error, check:

```sh
tail -n 200 /var/log/plesk/panel.log
tail -n 200 /var/log/sw-cp-server/error_log
tail -n 200 /usr/local/psa/var/modules/node-manager-pm2/logs/node-manager-pm2.log
```

On some Plesk builds, the panel log is stored at:

```sh
tail -n 200 /usr/local/psa/admin/logs/panel.log
```

## PM2 Startup Persistence

The extension runs PM2 under each domain system user with `PM2_HOME` set to
`<domain-home>/.pm2`. Administrators should configure startup persistence in a
way that matches the server's OS and service policy. The extension saves PM2
state after create/delete operations with `pm2 save`.
