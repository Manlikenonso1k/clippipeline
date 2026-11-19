#!/usr/bin/env bash
# Runs ON the production server (called by .github/workflows/deploy.yml, or by hand).
# Safe by design: fast-forward pull only, composer install (never update), migrate --force only.
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/nonsostudio.com/public_html/clippipeline}"
PHP="${PHP:-/opt/alt/php83/usr/bin/php}"
BRANCH="${BRANCH:-main}"
COMPOSER="${COMPOSER:-$(command -v composer || echo "$HOME/composer.phar")}"

cd "$APP_DIR"
echo "==> Deploying $BRANCH in $APP_DIR"

"$PHP" artisan down --retry=15 || true
trap '"$PHP" artisan up || true' EXIT

git fetch --prune origin "$BRANCH"
git checkout -q "$BRANCH"
git pull --ff-only origin "$BRANCH"
echo "==> Now at $(git log --oneline -1)"

"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --prefer-dist
"$PHP" artisan migrate --force
"$PHP" artisan optimize:clear
"$PHP" artisan optimize
"$PHP" artisan queue:restart || true

echo "==> Deploy finished"
