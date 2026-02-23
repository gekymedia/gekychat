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
$remoteCmd = 'cd /home/gekymedia/web/chat.gekychat.com/public_html && git pull origin main && composer install --no-dev --optimize-autoloader && npm ci --silent && npm run build && php artisan migrate --force && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan optimize && php artisan queue:restart && (command -v supervisorctl >/dev/null 2>&1 && sudo supervisorctl reread && sudo supervisorctl update || true)'
ssh root@gekymedia.com $remoteCmd
