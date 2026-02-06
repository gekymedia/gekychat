# Check live server logs (chat.gekychat.com on gekymedia.com)
# Run from gekychat repo: .\check_logs.ps1
# Optional: .\check_logs.ps1 -Tail 500   or   .\check_logs.ps1 -ErrorsOnly

param(
    [int]   $Tail = 200,      # Number of lines to show from end of Laravel log
    [switch] $ErrorsOnly,     # Only show lines containing error/exception
    [string] $LogFile = "storage/logs/laravel.log"
)

$basePath = "/home/gekymedia/web/chat.gekychat.com/public_html"
$remoteLog = "$basePath/$LogFile"

Write-Host "=== Live server logs (root@gekymedia.com) ===" -ForegroundColor Cyan
Write-Host "Log: $remoteLog" -ForegroundColor Gray
Write-Host ""

if ($ErrorsOnly) {
    Write-Host "Showing lines containing ERROR / Exception / error (last $Tail lines):" -ForegroundColor Yellow
    $cmd = "tail -n $Tail $remoteLog 2>/dev/null | grep -iE '(error|exception|fatal|critical)' || echo '(no matching lines)'"
} else {
    Write-Host "Last $Tail lines of Laravel log:" -ForegroundColor Yellow
    $cmd = "tail -n $Tail $remoteLog 2>/dev/null || echo 'Log file not found or empty.'"
}

ssh root@gekymedia.com $cmd

Write-Host ""
Write-Host "Done. Use -ErrorsOnly to filter for errors only; -Tail 500 for more lines." -ForegroundColor Gray
