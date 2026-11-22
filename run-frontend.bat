@echo off
title CashFlow iBanking - Frontend Web (Port 3000)
cd /d "%~dp0"
echo ========================================================
echo   CashFlow iBanking - Khoi dong Frontend Web tren Local
echo   Dia chi truy cap: http://127.0.0.1:3000
echo ========================================================
echo.
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -S 127.0.0.1:3000 -t frontend frontend/router.php
) else (
    php -S 127.0.0.1:3000 -t frontend frontend/router.php
)
pause
