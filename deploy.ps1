# GekyChat Production Deployment Script (PowerShell)
# Server: netcup RS 4000 — chat.gekychat.com
# Path: /var/www/chat.gekychat.com  (Hestia public_html symlinks here — do not change)
# SSH: root@159.195.249.203 (Windows OpenSSH alias: gekychat-netcup)
# Panel: Hestia https://cp.gekychat.com:8083 — see deploy/nakrotek-to-netcup/docs/post-hestia-paths.md
#
# Prerequisite: Supervisor configs already installed on the server
# (see deploy/supervisor/README.md). queue:restart reloads workers after deploy.

param(
    [switch]$SkipDesktopUpload
)

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $repoRoot

$sshHost = if ($env:GEKYCHAT_SSH_HOST) { $env:GEKYCHAT_SSH_HOST } else { "root@159.195.249.203" }
$appPath = "/var/www/chat.gekychat.com"
$appUser = "gekychat"
$remoteDownloads = "$appPath/public/downloads"

Write-Host "Committing and pushing local changes..." -ForegroundColor Cyan
git add .
git commit -m "Deploy: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
if ($LASTEXITCODE -ne 0) { Write-Host "No changes to commit" -ForegroundColor Yellow }
git push origin main
if ($LASTEXITCODE -ne 0) { throw "git push failed" }

Write-Host ("Deploying to production ({0}:{1})..." -f $sshHost, $appPath) -ForegroundColor Cyan
$scheduleCron = "* * * * * cd $appPath && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
$remoteCmd = @"
set -e
cd $appPath
git fetch origin main
git reset --hard origin/main
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
npm ci --silent
npm run build
php artisan migrate --force
php artisan conversations:sync-dm-columns-from-pivot || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
php artisan queue:restart
php artisan storage:link || true
chown -R ${appUser}:${appUser} storage bootstrap/cache public/downloads 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache
chmod 2775 storage/logs 2>/dev/null || true
chmod -R 755 public/downloads 2>/dev/null || true
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl reread || true
  supervisorctl update || true
fi
crontab -u $appUser -l 2>/dev/null | grep -Fq 'artisan schedule:run' || echo '$scheduleCron' | crontab -u $appUser -
php artisan schedule:run || true
echo Deploy OK
"@
ssh $sshHost $remoteCmd
if ($LASTEXITCODE -ne 0) { throw "Remote deploy failed" }

if (-not $SkipDesktopUpload) {
    $localDownloads = Join-Path $repoRoot "public\downloads"
    $binaries = @(
        Get-ChildItem -Path $localDownloads -Filter "GekyChat-Setup-*.exe" -ErrorAction SilentlyContinue
        Get-ChildItem -Path $localDownloads -Filter "*.zip" -ErrorAction SilentlyContinue
    )
    $archiveDir = Join-Path $localDownloads "archive"
    $archived = @()
    if (Test-Path $archiveDir) {
        $archived = @(Get-ChildItem -Path $archiveDir -Filter "GekyChat-Setup-*.exe" -ErrorAction SilentlyContinue)
    }
    if ($binaries.Count -gt 0 -or $archived.Count -gt 0) {
        Write-Host "Uploading desktop release(s) to $remoteDownloads ..." -ForegroundColor Cyan
        ssh $sshHost "mkdir -p $remoteDownloads/archive && chown -R ${appUser}:${appUser} $remoteDownloads"
        foreach ($file in $binaries) {
            $sizeMb = [math]::Round($file.Length / 1048576, 1)
            Write-Host ('  scp {0} ({1} MB)' -f $file.Name, $sizeMb) -ForegroundColor Gray
            scp $file.FullName "${sshHost}:${remoteDownloads}/"
            if ($LASTEXITCODE -ne 0) { throw "scp failed for $($file.Name)" }
        }
        foreach ($file in $archived) {
            $sizeMb = [math]::Round($file.Length / 1048576, 1)
            Write-Host ('  scp archive/{0} ({1} MB)' -f $file.Name, $sizeMb) -ForegroundColor Gray
            scp $file.FullName "${sshHost}:${remoteDownloads}/archive/"
            if ($LASTEXITCODE -ne 0) { throw "scp failed for archive/$($file.Name)" }
        }
        ssh $sshHost "chown -R ${appUser}:${appUser} $remoteDownloads 2>/dev/null || true"
        Write-Host "Desktop downloads uploaded." -ForegroundColor Green
    } else {
        Write-Host "No public/downloads/GekyChat-Setup-*.exe or *.zip found - skip desktop upload (build with gekychat_desktop/scripts/release-desktop-windows.ps1)" -ForegroundColor Yellow
    }
}

Write-Host "Done. Marketing: https://gekychat.com  Download: https://gekychat.com/download" -ForegroundColor Green
