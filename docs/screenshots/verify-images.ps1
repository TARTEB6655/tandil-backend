# PowerShell script to verify dashboard screenshots
# Run this script to check if all required images are present

Write-Host "Checking for dashboard screenshots..." -ForegroundColor Cyan
Write-Host ""

$requiredImages = @(
    "admin-dashboard.png",
    "client-dashboard.png",
    "hr-dashboard.png",
    "technician-dashboard.png",
    "supervisor-dashboard.png",
    "area-manager-dashboard.png"
)

$missingImages = @()
$foundImages = @()

foreach ($image in $requiredImages) {
    $imagePath = Join-Path $PSScriptRoot $image
    if (Test-Path $imagePath) {
        $fileInfo = Get-Item $imagePath
        $sizeKB = [math]::Round($fileInfo.Length / 1KB, 2)
        Write-Host "✓ Found: $image ($sizeKB KB)" -ForegroundColor Green
        $foundImages += $image
    } else {
        Write-Host "✗ Missing: $image" -ForegroundColor Red
        $missingImages += $image
    }
}

Write-Host ""
Write-Host "Summary:" -ForegroundColor Cyan
Write-Host "  Found: $($foundImages.Count) / $($requiredImages.Count)" -ForegroundColor $(if ($foundImages.Count -eq $requiredImages.Count) { "Green" } else { "Yellow" })
Write-Host "  Missing: $($missingImages.Count)" -ForegroundColor $(if ($missingImages.Count -eq 0) { "Green" } else { "Red" })

if ($missingImages.Count -gt 0) {
    Write-Host ""
    Write-Host "Missing images:" -ForegroundColor Yellow
    foreach ($missing in $missingImages) {
        Write-Host "  - $missing" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "All images should be saved in: $PSScriptRoot" -ForegroundColor Cyan

