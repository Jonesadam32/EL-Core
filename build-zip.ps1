# EL Core — Build Plugin ZIP
# Creates a properly formatted ZIP for WordPress upload on Linux servers.
#
# CRITICAL: Do NOT use Compress-Archive — it writes backslash path separators
# which Linux servers cannot extract into subdirectories. This script uses
# .NET ZipFile API to ensure forward slashes per the ZIP specification.

$version = "1.4.0"
$source  = "C:\Github\EL Core\el-core"
$downloadsZip = "C:\Users\Fred Jones\Downloads\el-core.zip"
$backupDir = "C:\Github\EL Core\old-versions\v$version"
$backupZip = "$backupDir\el-core-v$version.zip"

# Load .NET compression assembly
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

# Remove old ZIPs if they exist
if (Test-Path $downloadsZip) { Remove-Item $downloadsZip -Force }

# Create backup folder if needed
if (!(Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir | Out-Null }

# Create ZIP with forward-slash paths (required for Linux/WordPress extraction)
$zip = [System.IO.Compression.ZipFile]::Open($downloadsZip, [System.IO.Compression.ZipArchiveMode]::Create)

$files = Get-ChildItem -Path $source -Recurse -File
foreach ($file in $files) {
    $relativePath = $file.FullName.Substring($source.Length + 1)
    $entryName = $relativePath.Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName) | Out-Null
}

$zip.Dispose()

# Copy to backup location
Copy-Item $downloadsZip $backupZip -Force

# Also save the PHP file as a versioned backup
Copy-Item "$source\el-core.php" "$backupDir\el-core-v$version.php" -Force

Write-Host ""
Write-Host "Built v$version successfully!" -ForegroundColor Green
Write-Host "  WordPress upload:  $downloadsZip" -ForegroundColor Cyan
Write-Host "  Versioned backup:  $backupZip" -ForegroundColor Cyan
Write-Host ""
Write-Host "ZIP structure: files at root, forward-slash paths, no folder wrapper." -ForegroundColor Yellow
Write-Host "Upload at: WordPress Admin > Plugins > Add New > Upload Plugin"
Write-Host ""
Read-Host "Press Enter to close"
