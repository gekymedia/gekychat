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
Write-Host "📦 Pulling latest changes and running deployment on server..." -ForegroundColor Cyan
ssh $server "cd $projectPath && git pull origin main && bash scripts/deploy.sh"

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
