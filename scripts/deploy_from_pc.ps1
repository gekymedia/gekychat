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

# SSH and run deployment commands
Write-Host "📦 Cleaning conflicting files, pulling latest changes, and deploying..." -ForegroundColor Cyan
Write-Host ""

# Build single-line command string to avoid line ending issues
# Using semicolons to chain commands
$deployCommand = "cd $projectPath ; git stash 2>/dev/null || true ; git clean -fd 2>/dev/null || true ; rm -f DEPLOY.md 2>/dev/null || true ; echo '📥 Pulling latest changes...' ; git pull origin main 2>&1 || (git fetch origin && git reset --hard origin/main) ; echo '🚀 Running deployment...' ; bash scripts/deploy.sh"

ssh $server $deployCommand

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✅ Deployment completed successfully!" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "❌ Deployment failed. Check the error messages above." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "📋 Post-deployment checklist:" -ForegroundColor Yellow
Write-Host "  - [ ] Verify API routes are accessible: /api/v1/auth/phone"
Write-Host "  - [ ] Check admin panel: /admin/upload-settings"
Write-Host "  - [ ] Test video upload limits"
Write-Host "  - [ ] Verify database migrations ran successfully"
