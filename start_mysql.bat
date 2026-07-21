@echo off
echo Starting MySQL 8.4...
start /B "" "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\my.ini"
echo Waiting for MySQL to start...
timeout /t 8 /nobreak >nul
echo Done. MySQL is running on port 3306.
echo.
echo To stop MySQL, run: stop_mysql.bat
