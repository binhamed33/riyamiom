@echo off
title LexPro - تنصيب كامل (ضغطة واحدة)
color 0A
chcp 65001 >nul

:: تشغيل كـ Administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [*] يتم تشغيل الملف كـ Administrator...
    powershell -NoProfile -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

cls
echo =============================================
echo       بصمة المحامي - تنصيب كامل تلقائي
echo =============================================
echo.
echo يتم تحميل وتثبيت كل الأدوات... ارتاح ☕
echo.

set "TOOLS=C:\tools"
set "LAW_DIR=%~dp0"
set "LOG=%TEMP%\lexpro-install.log"
echo [%date% %time%] بدء التنصيب > "%LOG%"

:: ==========================================
:: 1. Git
:: ==========================================
echo [1/5] Git...
where git >nul 2>&1
if %errorlevel% neq 0 (
    echo   [*] جاري تحميل Git...
    curl -sL -o "%TEMP%\git-install.exe" "https://github.com/git-for-windows/git/releases/download/v2.47.0.windows.2/Git-2.47.0.2-64-bit.exe" >> "%LOG%" 2>&1
    echo   [*] جاري تثبيت Git...
    "%TEMP%\git-install.exe" /VERYSILENT /NORESTART /NOCANCEL /SP- /CLOSEAPPLICATIONS /RESTARTAPPLICATIONS /COMPONENTS="icons,ext\reg\shellhere,assoc,assoc_sh" >> "%LOG%" 2>&1
    echo   [✔] تم تثبيت Git
) else (
    echo   [✔] Git موجود
)

:: ==========================================
:: 2. PHP
:: ==========================================
echo [2/5] PHP...
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo   [*] جاري تحميل PHP 8.3...
    if not exist "%TOOLS%" mkdir "%TOOLS%"
    curl -sL -o "%TEMP%\php.zip" "https://windows.php.net/downloads/releases/php-8.3.15-nts-Win32-vs16-x64.zip" >> "%LOG%" 2>&1
    echo   [*] جاري فك الضغط...
    powershell -NoProfile -Command "Expand-Archive -Path '%TEMP%\php.zip' -DestinationPath '%TOOLS%\php' -Force" >> "%LOG%" 2>&1
    echo   [*] جاري إضافة PHP إلى PATH...
    setx /M PATH "%TOOLS%\php;%PATH%" >nul
    echo   [✔] تم تثبيت PHP
) else (
    echo   [✔] PHP موجود
)

:: refresh PATH
set "PATH=%TOOLS%\php;%PATH%"

:: ==========================================
:: 3. Composer
:: ==========================================
echo [3/5] Composer...
where composer >nul 2>&1
if %errorlevel% neq 0 (
    echo   [*] جاري تحميل Composer...
    curl -sL -o "%TEMP%\composer-setup.exe" "https://getcomposer.org/Composer-Setup.exe" >> "%LOG%" 2>&1
    echo   [*] جاري التثبيت...
    "%TEMP%\composer-setup.exe" /VERYSILENT /NORESTART >> "%LOG%" 2>&1
    echo   [✔] تم تثبيت Composer
) else (
    echo   [✔] Composer موجود
)

:: ==========================================
:: 4. Cloudflare Tunnel
:: ==========================================
echo [4/5] Cloudflare Tunnel...
where cloudflared >nul 2>&1
if %errorlevel% neq 0 (
    echo   [*] جاري تحميل cloudflared...
    curl -sL -o "%WINDIR%\System32\cloudflared.exe" "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe" >> "%LOG%" 2>&1
    echo   [✔] تم تثبيت cloudflared
) else (
    echo   [✔] cloudflared موجود
)

:: ==========================================
:: 5. تجهيز المشروع
:: ==========================================
echo [5/5] تجهيز المشروع...

:: تشغيل composer install
echo   [*] تثبيت مكتبات Laravel...
cd /d "%LAW_DIR%"
call composer install --no-interaction --prefer-dist >> "%LOG%" 2>&1
if %errorlevel% equ 0 ( echo   [✔] تم تثبيت المكتبات ) else ( echo   [!] فشل - شغل manual: composer install && pause && exit /b )

:: انشاء .env
if not exist ".env" (
    copy .env.example .env >nul
    echo   [✔] تم إنشاء .env
) else (
    echo   [✔] .env موجود
)

:: توليد APP_KEY
php artisan key:generate --force >> "%LOG%" 2>&1
echo   [✔] تم توليد APP_KEY

:: تشغيل الترحيلات
php artisan migrate --force >> "%LOG%" 2>&1
echo   [✔] تم تشغيل الترحيلات

:: ==========================================
echo.
echo =============================================
echo           تم تثبيت جميع الأدوات! 
echo =============================================
echo.
echo الحين نبدأ تجهيز Cloudflare Tunnel...
echo راح يفتح المتصفح عشان تسجل دخول Cloudflare
echo بعدها كل شيء يكمل تلقائي...
echo.

pause
echo.

:: ==========================================
:: تشغيل setup.bat عشان يكمل Cloudflare + الخدمات
:: ==========================================
call "%~dp0setup.bat"

echo.
echo =============================================
echo           ألف مبروك! الجهاز جاهز ✅
echo =============================================
echo.
echo الموقع:  https://office.riyami.om
echo.
echo الخدمات اللي تشتغل تلقائياً مع Windows:
echo   - LexPro-Laravel (خادم Laravel)
echo   - LexPro-Tunnel (Cloudflare Tunnel)
echo   - LexPro-Watchdog (مراقبة ذاتية)
echo.
echo أي خدمة تنطفئ، الـ watchdog يشغلها تلقائياً
echo.
echo تقدر تسوي Restart للجهاز وكل شيء يشتغل لوحده
echo =============================================
echo.
pause
