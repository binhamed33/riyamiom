@echo off
title LexPro - Full Server Start
color 0A

echo ============================================
echo    Starting LexPro Server...
echo ============================================
echo.

cd /d C:\Users\Admin\Documents\law-office

REM Start Laravel in background
echo [1/2] Starting Laravel on port 8000...
start "Laravel Server" cmd /c "php artisan serve --host=0.0.0.0 --port=8000"
timeout /t 3 /nobreak >nul
echo [OK] Laravel running!
echo.

REM Start Cloudflare Tunnel
echo [2/2] Starting Cloudflare Tunnel...
echo.
echo ============================================
echo    LOOK FOR THE URL BELOW (https://xxxxx...)
echo ============================================
echo.
"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --config C:\Users\Admin\.cloudflared\config.yml run law-office

pause
