#!/bin/bash

# GekyChat Production Deployment Script
# Server: netcup RS 4000 (chat.gekychat.com)
# Path: /var/www/chat.gekychat.com
# SSH: root@159.195.249.203  (alias: gekychat-netcup)

set -euo pipefail

SSH_HOST="${SSH_HOST:-root@159.195.249.203}"
APP_PATH="${APP_PATH:-/var/www/chat.gekychat.com}"

echo "Committing and pushing local changes..."
git add .
git commit -m "Deploy: $(date +"%Y-%m-%d %H:%M:%S")" || echo "No changes to commit"
git push origin main

echo "Deploying to production ($SSH_HOST:$APP_PATH)..."
ssh "$SSH_HOST" "bash -s" <<EOF
set -euo pipefail
cd '$APP_PATH'
git fetch origin main
git reset --hard origin/main
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
npm ci --silent
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
php artisan queue:restart
php artisan storage:link || true
chown -R gekychat:gekychat storage bootstrap/cache public/downloads 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl reread || true
  supervisorctl update || true
fi
echo Deploy OK
EOF
