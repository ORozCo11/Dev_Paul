param([switch]$SkipInstall)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$repoRoot = $PSScriptRoot
$backendPath = Join-Path $repoRoot 'backend-api'
$frontendPath = Join-Path $repoRoot 'frontend-spa'

function Invoke-DemoStep {
    param([string]$Directory, [string]$Program, [string[]]$Arguments)
    Push-Location -LiteralPath $Directory
    try {
        & $Program @Arguments
        if ($LASTEXITCODE -ne 0) { throw "$Program failed (exit $LASTEXITCODE). Setup stopped." }
    } finally {
        Pop-Location
    }
}

Get-Command php -ErrorAction Stop | Out-Null
if (!$SkipInstall) {
    Get-Command composer -ErrorAction Stop | Out-Null
    Get-Command npm -ErrorAction Stop | Out-Null
}

$backendEnv = Join-Path $backendPath '.env'
if (!(Test-Path -LiteralPath $backendEnv)) {
    Copy-Item -LiteralPath (Join-Path $backendPath '.env.example') -Destination $backendEnv
}
$backendSettings = Get-Content -LiteralPath $backendEnv -Raw
$environmentMatch = [regex]::Match($backendSettings, '(?m)^APP_ENV\s*=\s*["'']?([^\s"''#]+)')
$demoEnvironment = if ($env:APP_ENV) { $env:APP_ENV } else { $environmentMatch.Groups[1].Value }
if ($demoEnvironment -notin @('local', 'testing')) {
    throw 'Demo setup only runs with APP_ENV=local or testing. Existing configuration was preserved.'
}

if (!$SkipInstall) {
    Invoke-DemoStep $backendPath composer @('install', '--no-interaction', '--prefer-dist')
    Invoke-DemoStep $frontendPath npm @('ci')
}

$frontendEnv = Join-Path $frontendPath '.env'
if (!(Test-Path -LiteralPath $frontendEnv)) {
    Copy-Item -LiteralPath (Join-Path $frontendPath '.env.example') -Destination $frontendEnv
}
$sqlitePath = Join-Path $backendPath 'database/database.sqlite'
if (!(Test-Path -LiteralPath $sqlitePath)) { New-Item -ItemType File -Path $sqlitePath | Out-Null }

Invoke-DemoStep $backendPath php @('artisan', 'config:clear')
if (!($env:APP_KEY) -and $backendSettings -notmatch '(?m)^APP_KEY\s*=\s*["'']?[^\s"'']+') {
    Invoke-DemoStep $backendPath php @('artisan', 'key:generate')
}
Invoke-DemoStep $backendPath php @('artisan', 'demo:sync')

Write-Host ''
Write-Host 'Demo setup complete. Existing .env files and application keys were preserved.'
Write-Host 'Start API: cd backend-api; php artisan serve --port=8001'
Write-Host 'Start SPA in another terminal: cd frontend-spa; npm run dev'
Write-Host 'Open http://localhost:5173 and sign in again. See VMS Docs/Sample-Data.md for accounts.'
