# GekyChat Deployment Script for Windows PC
# This script connects to the server and runs the deployment

# GekyChat Deployment Script for Windows PC
# Usage: .\scripts\deploy_from_pc.ps1
# Or: pwsh scripts\deploy_from_pc.ps1

# SSH Server Details
$server = "root@gekymedia.com"
$projectPath = "/home/gekymedia/web/chat.gekychat.com/public_html"

Write-Host "🚀 Starting GekyChat deployment from PC..." -ForegroundColor Cyan
Write-Host "📡 Connecting to server: $server" -ForegroundColor Yellow
Write-Host "📍 Project path: $projectPath" -ForegroundColor Yellow
Write-Host ""

# SSH and run deployment script directly
Write-Host "📦 Cleaning conflicting files, pulling latest changes, and deploying..." -ForegroundColor Cyan
Write-Host ""

# First, handle any conflicting files on the server, then pull and deploy
# This handles untracked files that would be overwritten by the merge
$deployCommand = @"
cd $projectPath
echo '🧹 Cleaning up conflicting untracked files...'
git stash || true
git clean -fd || true
rm -f DEPLOY.md || true
echo '📥 Pulling latest changes...'
git pull origin main || (git fetch origin && git reset --hard origin/main)
echo '🚀 Running deployment...'
bash scripts/deploy.sh
"@

ssh $server $deployCommand

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Deployment completed successfully!" -ForegroundColor Green
} else {
    Write-Host "❌ Deployment failed. Check the error messages above." -ForegroundColor Red
    exit 1
}

Write-Host "`n📋 Post-deployment checklist:" -ForegroundColor Yellow
Write-Host "  - [ ] Verify API routes are accessible: /api/v1/auth/phone"
Write-Host "  - [ ] Check admin panel: /admin/upload-settings"
Write-Host "  - [ ] Test video upload limits"
Write-Host "  - [ ] Verify database migrations ran successfully"
