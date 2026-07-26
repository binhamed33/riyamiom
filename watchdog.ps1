# LexPro Watchdog - Self-Healing Monitor
# Run every 2 minutes via Task Scheduler

$logFile = "C:\server\logs\watchdog.log"
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

function Write-Log {
    param($msg)
    "$timestamp $msg" | Out-File -FilePath $logFile -Append
    Write-Output "$timestamp $msg"
}

Write-Log "[WATCHDOG] Checking services..."

# 1. MySQL
$mysql = Get-Process mysqld -ErrorAction SilentlyContinue
if (-not $mysql) {
    Write-Log "[ALERT] MySQL is DOWN. Restarting..."
    Start-Process -NoNewWindow -FilePath "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\ProgramData\MySQL\my.ini"
    Start-Sleep -Seconds 5
    $retry = Get-Process mysqld -ErrorAction SilentlyContinue
    if ($retry) { Write-Log "[OK] MySQL restarted successfully" }
    else { Write-Log "[FAIL] MySQL could not be started" }
} else {
    Write-Log "[OK] MySQL running (PID: $($mysql.Id))"
}

# 2. PHP (Laravel)
$php = Get-Process php -ErrorAction SilentlyContinue
$portCheck = netstat -ano | Select-String ":8000.*LISTEN"
if (-not $portCheck) {
    Write-Log "[ALERT] Laravel is DOWN. Restarting..."
    $laravelDir = "C:\Users\Admin\Documents\law-office"
    if (Test-Path $laravelDir) {
        Start-Process -NoNewWindow -FilePath "php" -ArgumentList "artisan serve --host=0.0.0.0 --port=8000" -WorkingDirectory $laravelDir
        Start-Sleep -Seconds 3
        $retry = netstat -ano | Select-String ":8000.*LISTEN"
        if ($retry) { Write-Log "[OK] Laravel restarted successfully" }
        else { Write-Log "[FAIL] Laravel could not be started" }
    }
} else {
    Write-Log "[OK] Laravel running on port 8000"
}

# 3. Cloudflare Tunnel
$tunnel = Get-Process cloudflared -ErrorAction SilentlyContinue
if (-not $tunnel) {
    Write-Log "[ALERT] Cloudflare Tunnel is DOWN. Restarting..."
    $tunnelDir = "C:\Users\Admin\.cloudflared"
    $configFile = "$tunnelDir\config.yml"
    if (Test-Path $tunnelDir) {
        Start-Process -NoNewWindow -FilePath "cloudflared.exe" -ArgumentList "tunnel run" -WorkingDirectory $tunnelDir
        Start-Sleep -Seconds 5
        $retry = Get-Process cloudflared -ErrorAction SilentlyContinue
        if ($retry) { Write-Log "[OK] Cloudflare Tunnel restarted successfully" }
        else { Write-Log "[FAIL] Cloudflare Tunnel could not be started" }
    }
} else {
    Write-Log "[OK] Cloudflare Tunnel running (PID: $($tunnel.Id))"
}

# 4. Health Check via HTTP
try {
    $response = Invoke-WebRequest -Uri "http://127.0.0.1:8000/health" -TimeoutSec 5 -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Log "[OK] HTTP Health Check: 200 OK"
    } else {
        Write-Log "[WARN] HTTP Health Check: $($response.StatusCode)"
    }
} catch {
    Write-Log "[ALERT] HTTP Health Check FAILED: $_"
}

Write-Log "[WATCHDOG] Check complete."
Write-Log ""
