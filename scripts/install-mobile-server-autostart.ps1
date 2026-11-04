param(
    [switch]$Remove
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$serverScript = Join-Path $PSScriptRoot 'start-mobile-server.ps1'
$startupDirectory = [Environment]::GetFolderPath('Startup')
$shortcutPath = Join-Path $startupDirectory 'McDonalds Reservations API.lnk'

if ($Remove) {
    if (Test-Path -LiteralPath $shortcutPath) {
        Remove-Item -LiteralPath $shortcutPath -Force
    }

    Write-Host 'McDonalds Reservations API automatic startup was removed.'
    return
}

if (-not (Test-Path -LiteralPath $serverScript)) {
    throw 'The mobile server launcher could not be found.'
}

$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = (Get-Command powershell.exe -ErrorAction Stop).Source
$shortcut.Arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$serverScript`" -Port 8000"
$shortcut.WorkingDirectory = $projectRoot
$shortcut.WindowStyle = 7
$shortcut.Description = "Start the McDonald's Reservations Laravel API for the mobile app"
$shortcut.Save()

Write-Host 'McDonalds Reservations API will now start automatically after Windows sign-in.'
Write-Host "Shortcut: $shortcutPath"
Write-Host 'To remove it later, run this script again with -Remove.'
