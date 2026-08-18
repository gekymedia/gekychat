# GekyChat Production Deployment Script (PowerShell)
# Server: gekymedia.com
# Path: /home/gekymedia/web/chat.gekychat.com/public_html
#
# Prerequisite: One-time supervisor setup on server (see deploy/supervisor/README.md).
# Supervisor runs Laravel queue workers; queue:restart signals them to reload after deploy.

param(
    [switch]$SkipDesktopUpload
)

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $repoRoot

$sshHost = "root@gekymedia.com"
$appPath = "/home/gekymedia/web/chat.gekychat.com/public_html"
$remoteDownloads = "$appPath/public/downloads"

Write-Host "Committing and pushing local changes..." -ForegroundColor Cyan
git add .
git commit -m "Deploy: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
if ($LASTEXITCODE -ne 0) { Write-Host "No changes to commit" -ForegroundColor Yellow }
git push origin main
if ($LASTEXITCODE -ne 0) { throw "git push failed" }

Write-Host "Deploying to production..." -ForegroundColor Cyan
# After migrate: repair conversations.user_one_id/user_two_id from conversation_user (idempotent)
# Reset server tree to origin/main (avoids merge failures from hot-patches or stray deploy commits on the server).
# Ensure Laravel scheduler cron for gekymedia (schedule:run every minute).
$scheduleCron = "* * * * * cd $appPath && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
$remoteCmd = @"
cd $appPath && git fetch origin main && git reset --hard origin/main && composer install --no-dev --optimize-autoloader && npm ci --silent && npm run build && php artisan migrate --force && php artisan conversations:sync-dm-columns-from-pivot && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan optimize && php artisan queue:restart && (command -v supervisorctl >/dev/null 2>&1 && sudo supervisorctl reread && sudo supervisorctl update || true) && (crontab -u gekymedia -l 2>/dev/null | grep -Fq 'artisan schedule:run' || { crontab -u gekymedia -l 2>/dev/null; echo '$scheduleCron'; } | crontab -u gekymedia -) && php artisan schedule:run && chown -R gekymedia:gekymedia storage bootstrap/cache public/downloads && chmod 2775 storage/logs && chmod -R 755 public/downloads
"@
ssh $sshHost $remoteCmd
if ($LASTEXITCODE -ne 0) { throw "Remote deploy failed" }

if (-not $SkipDesktopUpload) {
    $localDownloads = Join-Path $repoRoot "public\downloads"
    $zips = @(Get-ChildItem -Path $localDownloads -Filter "*.zip" -ErrorAction SilentlyContinue)
    if ($zips.Count -gt 0) {
        Write-Host "Uploading desktop release(s) to $remoteDownloads ..." -ForegroundColor Cyan
        ssh $sshHost "mkdir -p $remoteDownloads && chown gekymedia:gekymedia $remoteDownloads"
        foreach ($zip in $zips) {
            Write-Host "  scp $($zip.Name) ($([math]::Round($zip.Length / 1MB, 1)) MB)" -ForegroundColor Gray
            scp $zip.FullName "${sshHost}:${remoteDownloads}/"
            if ($LASTEXITCODE -ne 0) { throw "scp failed for $($zip.Name)" }
        }
        ssh $sshHost "chown gekymedia:gekymedia $remoteDownloads/* 2>/dev/null || true"
        Write-Host "Desktop downloads uploaded." -ForegroundColor Green
    } else {
        Write-Host "No public/downloads/*.zip found - skip desktop upload (build with gekychat_desktop/scripts/release-desktop-windows.ps1)" -ForegroundColor Yellow
    }
}

Write-Host "Done. Download page: https://gekychat.com/download" -ForegroundColor Green
