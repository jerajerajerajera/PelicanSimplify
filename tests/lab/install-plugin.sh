#!/usr/bin/env bash
# Copies the Simplify checkout (a branch) into a lab panel and (re)installs it. install-plugin.sh <panel-dir> <plugin-checkout> [php]
set -Eeuo pipefail
PANEL=$1; SRC=$2; PHP=${3:-php8.3}
mkdir -p "$PANEL/plugins/pelican-simplify"
rm -rf "$PANEL/plugins/pelican-simplify"; mkdir -p "$PANEL/plugins/pelican-simplify"; tar -C "$SRC" --exclude=.git --exclude=dist --exclude=node_modules -cf - . | tar -C "$PANEL/plugins/pelican-simplify" -xf -
cd "$PANEL"
$PHP artisan view:clear --quiet; $PHP artisan cache:clear --quiet; $PHP artisan config:clear --quiet
$PHP artisan p:plugin:install pelican-simplify --no-interaction >/dev/null 2>&1 || true
$PHP artisan p:plugin:list | grep -E 'PelicanSimplify .*\| enabled' >/dev/null || { echo "PelicanSimplify not enabled in $PANEL"; exit 1; }
echo "installed $(cd "$SRC" && git rev-parse --short HEAD) into $PANEL"
