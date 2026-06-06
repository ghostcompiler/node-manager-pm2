#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

cd "$ROOT_DIR"
VERSION="$(php -r '$xml = simplexml_load_file("meta.xml"); echo trim((string) $xml->version);')"
OUT="${ROOT_DIR}/node-manager-pm2-${VERSION}.zip"
if command -v npm >/dev/null 2>&1; then
  if [ ! -d node_modules ]; then
    if [ -f package-lock.json ]; then
      npm ci --ignore-scripts --legacy-peer-deps
    else
      npm install --ignore-scripts --legacy-peer-deps
    fi
  fi
  npm run build
else
  printf '%s\n' "npm is required to build the React UI bundle." >&2
  exit 1
fi

rm -f "$OUT"
COPYFILE_DISABLE=1 zip -r "$OUT" \
  meta.xml DESCRIPTION.md CHANGES.md README.md THIRD_PARTY_NOTICES.md \
  htdocs plib sbin var _meta docs packaging \
  -x "var/data/*" "var/backups/*" ".git/*" "*.DS_Store" "__MACOSX/*"

printf '%s\n' "$OUT"
