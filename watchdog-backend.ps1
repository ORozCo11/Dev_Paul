# VMS Backend Watchdog - keeps Laravel PHP server alive on port 8001
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$phpExe    = "C:\webdev\PHP\php.exe"
$backendDir = Join-Path $projectRoot "backend-api"
$artisan   = Join-Path $backendDir "artisan"
$logDir    = Join-Path $projectRoot "logs"
$logFile   = Join-Path $logDir "backend-watchdog.log"

New-Item -ItemType Directory -Force -Path $logDir | Out-Null

function Log($msg) {
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    "$ts  $msg" | Tee-Object -FilePath $logFile -Append | Out-Null
}

Log "Watchdog started. Watching $backendDir"

$sqlitePath = Join-Path $backendDir "database\database.sqlite"
if (-not (Test-Path $sqlitePath)) {
    Log "database.sqlite missing - creating it before first migration."
    New-Item -ItemType File -Force -Path $sqlitePath | Out-Null
}

while ($true) {
    $running = Get-NetTCPConnection -LocalPort 8001 -State Listen -ErrorAction SilentlyContinue
    if (-not $running) {
        Log "Port 8001 not listening - syncing database schema..."
        & $phpExe $artisan migrate --force *>> $logFile

        Log "Starting Laravel server..."
        Start-Process -FilePath $phpExe `
            -ArgumentList "`"$artisan`" serve --host=127.0.0.1 --port=8001" `
            -WorkingDirectory $backendDir `
            -WindowStyle Hidden
        Log "Laravel server process launched."
    }
    Start-Sleep -Seconds 15
}
