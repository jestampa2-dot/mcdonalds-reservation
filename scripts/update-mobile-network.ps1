$repoRoot = Split-Path -Parent $PSScriptRoot
$mobileDir = Join-Path $repoRoot 'mobile'
$envPath = Join-Path $mobileDir '.env'

$ipv4Addresses = ipconfig |
  Select-String 'IPv4 Address' |
  ForEach-Object { ($_.Line -replace '.*:\s*', '').Trim() } |
  Where-Object { $_ -and $_ -notlike '127.*' -and $_ -notlike '169.254.*' }

$currentIp = $ipv4Addresses | Select-Object -First 1

if (-not $currentIp) {
  throw 'No active IPv4 address was found. Connect the laptop to Wi-Fi or a hotspot first.'
}

$baseUrlLine = "EXPO_PUBLIC_API_BASE_URL=http://${currentIp}:8000"
$envContent = if (Test-Path $envPath) { Get-Content -Raw $envPath } else { '' }

if ($envContent -match '(?m)^EXPO_PUBLIC_API_BASE_URL=.*$') {
  $updatedContent = [regex]::Replace($envContent, '(?m)^EXPO_PUBLIC_API_BASE_URL=.*$', $baseUrlLine)
} elseif ([string]::IsNullOrWhiteSpace($envContent)) {
  $updatedContent = "$baseUrlLine`r`n"
} else {
  $updatedContent = $envContent.TrimEnd() + "`r`n" + $baseUrlLine + "`r`n"
}

Set-Content -Path $envPath -Value $updatedContent -Encoding ascii

Write-Host "Updated mobile/.env"
Write-Host "EXPO_PUBLIC_API_BASE_URL=http://${currentIp}:8000"
Write-Host ''
Write-Host 'Next steps:'
Write-Host '1. php artisan serve --host=0.0.0.0 --port=8000'
Write-Host '2. cd mobile'
Write-Host '3. npm.cmd run start'
