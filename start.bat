@echo off
title LexPro
color 0B

echo =============================================
echo       Starting LexPro
echo =============================================
echo.

cd /d "%~dp0"

:: Kill stale PHP on port 8000
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8000 "') do (
    taskkill /f /pid %%a >nul 2>&1
    timeout /t 1 /nobreak >nul
)

:: Start PHP
echo [..] Starting PHP server...
start /B php artisan serve --host=0.0.0.0 --port=8000 --no-reload
timeout /t 2 /nobreak >nul
echo [OK] PHP server started

:: Kill stale cloudflared
taskkill /f /im cloudflared.exe >nul 2>&1
timeout /t 1 /nobreak >nul

:: Start tunnel
echo [..] Starting Cloudflare Tunnel...
start /B cloudflared tunnel run law-office
echo [OK] Cloudflare Tunnel started

echo.
echo =============================================
echo  Site: https://office.riyami.om
echo  Local: http://localhost:8000
echo =============================================
echo.
pause
