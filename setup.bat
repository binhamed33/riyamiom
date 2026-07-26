@echo off
title LexPro - تجهيز جهاز المكتب
color 0B
chcp 65001 >nul

echo =============================================
echo       تجهيز جهاز المكتب - LexPro
echo =============================================
echo.

:: 1. التحقق من الأدوات
echo [1/6] التحقق من الأدوات...
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] PHP غير موجود!
    echo    حمّله من: https://windows.php.net/download/
    echo    وضيفه إلى PATH، ثم شغّل الملف مرة ثانية
    pause
    exit /b
)
echo   [✔] PHP موجود

where cloudflared >nul 2>&1
if %errorlevel% neq 0 (
    echo   [*] يتم تحميل cloudflared...
    curl -sL -o "%TEMP%\cloudflared.exe" "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
    move /y "%TEMP%\cloudflared.exe" "%WINDIR%\System32\cloudflared.exe" >nul
    echo   [✔] تم تثبيت cloudflared
) else (
    echo   [✔] cloudflared موجود
)

:: 2. تسجيل الدخول إلى Cloudflare
echo.
echo [2/6] تسجيل الدخول إلى Cloudflare...
echo   سيفتح المتصفح - سجل الدخول إلى Cloudflare
echo   بعد تسجيل الدخول، ارجع إلى هذه النافذة
echo.
cloudflared tunnel login
if %errorlevel% neq 0 (
    echo [!] فشل تسجيل الدخول
    pause
    exit /b
)
echo   [✔] تم تسجيل الدخول

:: 3. إنشاء الـ Tunnel
echo.
echo [3/6] إنشاء الـ Tunnel...
cloudflared tunnel list | findstr lexpro-office >nul
if %errorlevel% equ 0 (
    echo   [✔] الـ Tunnel موجود مسبقاً
) else (
    cloudflared tunnel create lexpro-office
    echo   [✔] تم إنشاء الـ Tunnel
)

:: 4. إضافة DNS
echo.
echo [4/6] إضافة DNS...
cloudflared tunnel route dns lexpro-office office.riyami.om
echo   [✔] DNS office.riyami.om → Tunnel

:: 5. إنشاء ملف الإعدادات
echo.
echo [5/6] إنشاء ملف config.yml...

set "credFile="
for %%f in ("%USERPROFILE%\.cloudflared\*.json") do (
    if not "%%~nxf"=="cert.json" set "credFile=%%f"
)

if "%credFile%"=="" (
    echo [!] ما لقيت ملف credentials!
    pause
    exit /b
)

(
echo tunnel: lexpro-office
echo credentials-file: %credFile%
echo ingress:
echo   - hostname: office.riyami.om
echo     service: http://localhost:8000
echo   - service: http_status:404
) > "%USERPROFILE%\.cloudflared\config.yml"
echo   [✔] تم إنشاء config.yml

:: 6. إضافة الخدمات إلى Task Scheduler
echo.
echo [6/6] إضافة الخدمات لتشغيل تلقائي...

mkdir "C:\server\scripts" 2>nul
mkdir "C:\server\logs" 2>nul

:: start-laravel.bat
(
echo @echo off
echo cd /d "%~dp0"
echo php artisan serve --host=0.0.0.0 --port=8000 --no-reload
) > "C:\server\scripts\start-laravel.bat"

:: start-tunnel.bat
(
echo @echo off
echo cd /d "%USERPROFILE%\.cloudflared"
echo cloudflared tunnel run
) > "C:\server\scripts\start-tunnel.bat"

:: copy watchdog.ps1
copy "%~dp0watchdog.ps1" "C:\server\scripts\watchdog.ps1" >nul

:: Run PowerShell to create tasks
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    $action1 = New-ScheduledTaskAction -Execute 'C:\server\scripts\start-laravel.bat'; ^
    $trigger1 = New-ScheduledTaskTrigger -AtStartup -RandomDelay '00:00:30'; ^
    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest; ^
    Register-ScheduledTask -TaskName 'LexPro-Laravel' -Action $action1 -Trigger $trigger1 -Principal $principal -Force; ^
    $action2 = New-ScheduledTaskAction -Execute 'C:\server\scripts\start-tunnel.bat'; ^
    $trigger2 = New-ScheduledTaskTrigger -AtStartup -RandomDelay '00:01:00'; ^
    Register-ScheduledTask -TaskName 'LexPro-Tunnel' -Action $action2 -Trigger $trigger2 -Principal $principal -Force; ^
    $action3 = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument '-NoProfile -ExecutionPolicy Bypass -File C:\server\scripts\watchdog.ps1'; ^
    $trigger3 = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 2) -At (Get-Date '00:00:00') -Once -RepetitionDuration ([TimeSpan]::MaxValue); ^
    Register-ScheduledTask -TaskName 'LexPro-Watchdog' -Action $action3 -Trigger $trigger3 -Principal $principal -Force; ^
    New-NetFirewallRule -DisplayName 'LexPro-HTTP' -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow -ErrorAction SilentlyContinue

echo   [✔] تمت إضافة الخدمات إلى Task Scheduler

:: Final
echo.
echo =============================================
echo           تم التجهيز - جهاز المكتب جاهز
echo =============================================
echo.
echo موقعك:     https://office.riyami.om
echo الخادم:    http://localhost:8000
echo.
echo الخدمات اللي تشتغل مع Windows:
echo   - LexPro-Laravel
echo   - LexPro-Tunnel
echo   - LexPro-Watchdog (مراقبة كل دقيقتين)
echo.
echo خلص! تقدر تسوي Restart للجهاز وكل شيء يشتغل
echo =============================================
echo.

:: تشغيل الخدمات حالاً
echo جاري تشغيل الخدمات الآن...
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-ScheduledTask -TaskName LexPro-Laravel; Start-ScheduledTask -TaskName LexPro-Tunnel"
echo [✔] تم تشغيل الخدمات

pause
