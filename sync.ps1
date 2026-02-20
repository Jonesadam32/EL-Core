param(
    [string]$Message = "Session update $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
)

git add .
git commit -m $Message
git push

Write-Host "`nDone. GitHub is up to date." -ForegroundColor Green
