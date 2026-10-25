#!/usr/bin/env bash
# Installs one Pelican release at $LAB_DIR/panels/<version> (sqlite, no Wings), like CI's panel-smoke job.
# setup-panel.sh <version> [php] [port]   e.g. setup-panel.sh 1.0.0-rc.2 php8.4 8003
set -Eeuo pipefail
VERSION=$1; PHP=${2:-php8.3}; PORT=${3:-8001}; LAB_DIR=${LAB_DIR:-$HOME/pelican-lab}; DIR=$LAB_DIR/panels/$VERSION
if [[ ! -d $DIR ]]; then git clone --quiet --depth 1 --branch "v$VERSION" https://github.com/pelican-dev/panel.git "$DIR"; fi
cd "$DIR"
# The proxy refuses GitHub zip downloads, so everything comes from git; phpstan (dev, dist-only) is skipped and the
# factories' dependency (faker) is added back from source.
export COMPOSER_ALLOW_SUPERUSER=1
$PHP /usr/local/bin/composer install --no-interaction --no-progress --prefer-source --no-dev --no-scripts --quiet
$PHP artisan package:discover --quiet
# The factories need Faker (a dev package the proxy cannot download as a zip): one git checkout shared by every panel.
[[ -d $LAB_DIR/faker ]] || git clone --quiet --depth 1 --branch v1.24.1 https://github.com/FakerPHP/Faker.git "$LAB_DIR/faker"
if [[ ! -d vendor/fakerphp/faker ]]; then
  $PHP /usr/local/bin/composer config repositories.labfaker path "$LAB_DIR/faker"
  $PHP /usr/local/bin/composer require --no-interaction --no-progress --no-scripts --no-update --quiet fakerphp/faker:'*'
  $PHP /usr/local/bin/composer update fakerphp/faker --no-interaction --no-progress --no-scripts --prefer-source --no-dev --quiet
fi
sed -i "s/'version' => 'canary'/'version' => '$VERSION'/" config/app.php
[[ -f .env ]] || cp .env.example .env
sed -i 's/^APP_INSTALLED=.*/APP_INSTALLED=true/; s/^APP_DEBUG=.*/APP_DEBUG=true/; s#^APP_URL=.*#APP_URL=http://127.0.0.1:$PORT#' .env
grep -q '^DB_CONNECTION=sqlite' .env || printf 'DB_CONNECTION=sqlite\nQUEUE_CONNECTION=database\nCACHE_STORE=file\nSESSION_DRIVER=file\nMAIL_MAILER=log\n' >> .env
touch database/database.sqlite
$PHP artisan key:generate --force --quiet
$PHP artisan migrate --force --quiet
yarn install --silent 2>/dev/null || true
echo "READY $VERSION"
