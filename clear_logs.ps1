# Clear Laravel log on live server (chat.gekychat.com)
# Run from gekychat repo: .\clear_logs.ps1

$logPath = "/home/gekymedia/web/chat.gekychat.com/public_html/storage/logs/laravel.log"
Write-Host "Clearing live server log: $logPath" -ForegroundColor Cyan
ssh root@gekymedia.com "truncate -s 0 $logPath && echo 'Laravel log cleared. New entries will be fresh.'"
if ($LASTEXITCODE -eq 0) { Write-Host "Done." -ForegroundColor Green }
