$repoRoot = Split-Path -Parent $PSScriptRoot
$mobileDir = Join-Path $repoRoot 'mobile'
$envPath = Join-Path $mobileDir '.env'

$activeNetworks = [System.Net.NetworkInformation.NetworkInterface]::GetAllNetworkInterfaces() |
  Where-Object {
    $_.OperationalStatus -eq [System.Net.NetworkInformation.OperationalStatus]::Up -and
    $_.NetworkInterfaceType -ne [System.Net.NetworkInformation.NetworkInterfaceType]::Loopback
  } |
  ForEach-Object {
    $properties = $_.GetIPProperties()
    $ipv4 = $properties.UnicastAddresses |
      Where-Object {
        $_.Address.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork -and
        $_.Address.IPAddressToString -notlike '169.254.*'
      } |
      Select-Object -First 1
    $hasGateway = @($properties.GatewayAddresses | Where-Object { $_.Address.ToString() -notin @('0.0.0.0', '::') }).Count -gt 0

    if ($ipv4) {
      [pscustomobject]@{
        Address = $ipv4.Address.IPAddressToString
        HasGateway = $hasGateway
        IsWireless = $_.NetworkInterfaceType -eq [System.Net.NetworkInformation.NetworkInterfaceType]::Wireless80211
      }
    }
  }

$currentIp = $activeNetworks |
  Sort-Object -Property @{ Expression = 'HasGateway'; Descending = $true }, @{ Expression = 'IsWireless'; Descending = $true } |
  Select-Object -ExpandProperty Address -First 1

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
Write-Host '1. powershell -ExecutionPolicy Bypass -File .\scripts\start-mobile-server.ps1'
Write-Host '2. cd mobile'
Write-Host '3. npm.cmd run start'
