# EL Core — Build Plugin ZIP
# Creates a properly formatted ZIP for WordPress upload on Linux servers.
#
# CRITICAL: Do NOT use Compress-Archive — it writes backslash path separators
# which Linux servers cannot extract into subdirectories. This script uses
# .NET ZipFile API to ensure forward slashes per the ZIP specification.

$version = "1.11.2"
$source  = "C:\Github\EL Core\el-core"
$backupDir = "C:\Github\EL Core\old-versions\v$version"
$outputZip = "$backupDir\el-core-v$version.zip"
$releasesZip = "C:\Github\EL Core\releases\el-core-v$version.zip"

# Load .NET compression assembly
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

# Create backup folder if needed
if (!(Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir | Out-Null }

# Remove old ZIP if it exists
if (Test-Path $outputZip) { Remove-Item $outputZip -Force }

# Create ZIP with forward-slash paths and el-core/ folder wrapper
$zip = [System.IO.Compression.ZipFile]::Open($outputZip, [System.IO.Compression.ZipArchiveMode]::Create)

$files = Get-ChildItem -Path $source -Recurse -File
foreach ($file in $files) {
    $relativePath = $file.FullName.Substring($source.Length + 1)
    $entryName = 'el-core/' + $relativePath.Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName) | Out-Null
}

$zip.Dispose()

# Copy to releases folder
Copy-Item $outputZip $releasesZip -Force

# Save the PHP file as a versioned backup
Copy-Item "$source\el-core.php" "$backupDir\el-core-v$version.php" -Force

Write-Host ""
Write-Host "Built v$version successfully!" -ForegroundColor Green
Write-Host "  Releases folder:  $releasesZip" -ForegroundColor Cyan
Write-Host "  Versioned backup: $outputZip" -ForegroundColor Cyan
Write-Host ""
Write-Host "ZIP structure: el-core/ folder wrapper, forward-slash paths (Linux safe)." -ForegroundColor Yellow
Write-Host "Upload at: WordPress Admin > Plugins > Add New > Upload Plugin"
Write-Host ""
Read-Host "Press Enter to close"
