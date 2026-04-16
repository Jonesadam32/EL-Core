$version = "1.38.87"
$root    = $PSScriptRoot
$source  = Join-Path $root "el-core"
$backupDir = Join-Path $root "old-versions\v$version"
$outputZip = Join-Path $backupDir "el-core-v$version.zip"
$releasesDir = Join-Path $root "releases"
$releasesZip = Join-Path $releasesDir "el-core-v$version.zip"
$elCoreReleasesDir = Join-Path $root "el-core-releases"
$elCoreReleasesZip = Join-Path $elCoreReleasesDir "el-core-v$version.zip"
$downloadsZip = Join-Path $env:USERPROFILE "Downloads\el-core-v$version.zip"

Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

if (!(Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir | Out-Null }
if (Test-Path $outputZip) { Remove-Item $outputZip -Force }

$zip = [System.IO.Compression.ZipFile]::Open($outputZip, [System.IO.Compression.ZipArchiveMode]::Create)
$files = Get-ChildItem -Path $source -Recurse -File
foreach ($file in $files) {
    $relativePath = $file.FullName.Substring($source.Length + 1)
    $entryName = 'el-core/' + $relativePath.Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName) | Out-Null
}
$zip.Dispose()

if (!(Test-Path $releasesDir)) { New-Item -ItemType Directory -Path $releasesDir | Out-Null }
Copy-Item $outputZip $releasesZip -Force

if (!(Test-Path $elCoreReleasesDir)) { New-Item -ItemType Directory -Path $elCoreReleasesDir | Out-Null }
Copy-Item $outputZip $elCoreReleasesZip -Force

Copy-Item $outputZip $downloadsZip -Force
Copy-Item (Join-Path $source "el-core.php") (Join-Path $backupDir "el-core-v$version.php") -Force

Write-Host ""
Write-Host "Built v$version successfully!" -ForegroundColor Green
Write-Host "  old-versions:     $outputZip" -ForegroundColor Cyan
Write-Host "  releases:         $releasesZip" -ForegroundColor Cyan
Write-Host "  el-core-releases: $elCoreReleasesZip" -ForegroundColor Cyan
Write-Host "  Downloads:        $downloadsZip" -ForegroundColor Cyan
Write-Host ""
