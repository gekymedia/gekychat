# GekyChat Production Deployment Script (PowerShell)
# Server: gekymedia.com
# Path: /home/gekymedia/web/chat.gekychat.com/public_html
#
# Prerequisite: One-time supervisor setup on server (see deploy/supervisor/README.md).
# Supervisor runs Laravel queue workers; queue:restart signals them to reload after deploy.

Write-Host "Committing and pushing local changes..." -ForegroundColor Cyan
git add .
git commit -m "Deploy: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
if ($LASTEXITCODE -ne 0) { Write-Host "No changes to commit" -ForegroundColor Yellow }
git push origin main

Write-Host "Deploying to production..." -ForegroundColor Cyan
# After migrate: repair conversations.user_one_id/user_two_id from conversation_user (idempotent)
# Reset server tree to origin/main (avoids merge failures from hot-patches or stray deploy commits on the server).
$remoteCmd = 'cd /home/gekymedia/web/chat.gekychat.com/public_html && git fetch origin main && git reset --hard origin/main && composer install --no-dev --optimize-autoloader && npm ci --silent && npm run build && php artisan migrate --force && php artisan conversations:sync-dm-columns-from-pivot && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan optimize && php artisan queue:restart && (command -v supervisorctl >/dev/null 2>&1 && sudo supervisorctl reread && sudo supervisorctl update || true) && chown -R gekymedia:gekymedia storage bootstrap/cache && chmod 2775 storage/logs'
ssh root@gekymedia.com $remoteCmd
