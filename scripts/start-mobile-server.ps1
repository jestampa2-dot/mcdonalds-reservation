param(
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$healthUrl = "http://127.0.0.1:$Port/api/mobile/home"

try {
    $health = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 3

    if ($health.StatusCode -eq 200) {
        Write-Host "McDonald's Reservations API is already running on port $Port."
        return
    }
} catch {
    # No healthy server is available yet, so start one below.
}

$php = (Get-Command php -ErrorAction Stop).Source
$router = Join-Path $projectRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'

if (-not (Test-Path -LiteralPath $router)) {
    throw "Laravel's development router was not found. Run composer install first."
}

Set-Location -LiteralPath $projectRoot

Write-Host "Starting McDonald's Reservations API at http://0.0.0.0:$Port"
Write-Host 'Payment uploads: 8 MB maximum; request body: 12 MB maximum.'

$phpArguments = @(
    '-d', 'upload_max_filesize=8M',
    '-d', 'post_max_size=12M',
    '-d', 'memory_limit=256M',
    '-d', 'max_execution_time=120',
    '-S', "0.0.0.0:$Port",
    $router
)

& $php @phpArguments
