# Fix status comments issue
$filePath = 'D:\projects\gekychat\resources\views\partials\sidebar_scripts.blade.php'

# Read the file
$content = Get-Content $filePath -Raw

# Fix 1: Change "body: commentText" to "comment: commentText" in the POST request
$content = $content -replace 'body: JSON\.stringify\(\{\s*body: commentText\s*\}', 'body: JSON.stringify({ comment: commentText })'

# Fix 2: Change "comment.body || comment.comment" to just "comment.comment" in the display
$content = $content -replace 'comment\.body \|\| comment\.comment', 'comment.comment'

# Write back
$content | Set-Content $filePath

Write-Host "✅ Fixed status comments issue"
