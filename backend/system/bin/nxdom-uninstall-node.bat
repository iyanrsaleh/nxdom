@echo off
setlocal EnableDelayedExpansion
cd /d "%~dp0..\.."

echo.
echo  ===============================================
echo    NexaUI - Node.js Server Uninstall
echo  ===============================================
echo.

REM Check environment from .env file
set "APP_ENV=production"
if exist ".env" (
    for /f "usebackq tokens=1,* delims==" %%a in (`findstr /i "^APP_ENV" .env 2^>nul`) do (
        set "TEMP_ENV=%%b"
        REM Remove everything after # (comments) and trim all spaces
        for /f "tokens=1 delims=# " %%c in ("!TEMP_ENV!") do set "APP_ENV=%%c"
    )
)

REM Only allow uninstall in development mode
if /i "!APP_ENV!" NEQ "development" (
    echo  [ERROR] Uninstall hanya dapat dijalankan di mode DEVELOPMENT!
    echo.
    echo  Environment saat ini: !APP_ENV!
    echo.
    echo  Untuk menjalankan uninstall:
    echo    1. Buka file .env
    echo    2. Ubah APP_ENV=production menjadi APP_ENV=development
    echo    3. Jalankan lagi: nxdom uninstall node
    echo.
    echo  [SECURITY] Ini untuk mencegah penghapusan tidak sengaja di production.
    echo.
    exit /b 1
)

echo  [ENVIRONMENT] Mode: DEVELOPMENT [OK]
echo.

REM Check if Node.js files exist
set "FILES_EXIST=0"
if exist "server.js" set "FILES_EXIST=1"
if exist "package.json" set "FILES_EXIST=1"
if exist "node_modules" set "FILES_EXIST=1"

if !FILES_EXIST! EQU 0 (
    echo  [INFO] Node.js server belum terinstall.
    echo.
    echo  Tidak ada file yang perlu dihapus:
    echo    - server.js
    echo    - package.json
    echo    - node_modules/
    echo.
    echo  Untuk install Node.js server:
    echo    nxdom install node
    echo.
    exit /b 0
)

echo  [WARNING] Perintah ini akan menghapus:
echo.
if exist "server.js" echo    - server.js
if exist "package.json" echo    - package.json
if exist "package-lock.json" echo    - package-lock.json
if exist "node_modules" echo    - node_modules/ ^(folder dan semua isinya^)
if exist "ecosystem.config.js" echo    - ecosystem.config.js
echo.
choice /C YN /M "  Apakah Anda yakin ingin menghapus"
if errorlevel 2 (
    echo.
    echo  [CANCELLED] Uninstall dibatalkan.
    echo.
    exit /b 0
)

echo.
echo  [1/5] Stopping Node.js server...

REM Stop PM2 process if exists (<nul avoids batch abort when stdin is piped e.g. echo Y|)
where pm2 >nul 2>&1
if not errorlevel 1 (
    call pm2 stop nxdom-node <nul >nul 2>&1
    call pm2 delete nxdom-node <nul >nul 2>&1
    if not errorlevel 1 (
        echo  [OK] PM2 process stopped and deleted
    )
)

REM Stop only NexaUI server.js — NOT taskkill /IM node.exe (kills IDE/Cursor helpers)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0nxdom-stop-node-server.ps1" <nul >nul 2>&1
if errorlevel 1 (
    echo  [INFO] No Node.js processes running
) else (
    echo  [OK] Node.js server stopped
    timeout /t 1 /nobreak >nul
)
echo.

echo  [2/5] Removing server.js...
if exist "server.js" (
    del /f /q "server.js" >nul 2>&1
    echo  [OK] server.js deleted
) else (
    echo  [INFO] server.js not found
)
echo.

echo  [3/5] Removing package files...
if exist "package.json" (
    del /f /q "package.json" >nul 2>&1
    echo  [OK] package.json deleted
)
if exist "package-lock.json" (
    del /f /q "package-lock.json" >nul 2>&1
    echo  [OK] package-lock.json deleted
)
echo.

echo  [4/5] Removing node_modules...
if exist "node_modules" (
    echo  [INFO] Deleting node_modules folder ^(this may take a moment^)...
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0nxdom-remove-node-modules.ps1" -ProjectRoot "%CD%" <nul
    if exist "node_modules" (
        echo  [ERROR] node_modules tidak dapat dihapus sepenuhnya
        echo  [INFO] Tutup proses yang memakai folder ini, lalu jalankan lagi:
        echo         nxdom uninstall node
        echo  [INFO] Atau hapus manual: Remove-Item -Recurse -Force .\node_modules
    ) else (
        echo  [OK] node_modules deleted
    )
) else (
    echo  [INFO] node_modules not found
)
echo.

echo  [5/5] Removing PM2 configuration...
if exist "ecosystem.config.js" (
    del /f /q "ecosystem.config.js" >nul 2>&1
    echo  [OK] ecosystem.config.js deleted
) else (
    echo  [INFO] ecosystem.config.js not found
)
echo.

echo  ===============================================
echo    Uninstall Complete!
echo  ===============================================
echo.
echo  Node.js server telah dihapus dari project.
echo.
echo  Untuk install kembali:
echo    nxdom install node
echo.
if exist "node_modules" (
    endlocal
    exit /b 1
)
endlocal
exit /b 0
