@echo off
setlocal EnableDelayedExpansion
cd /d "%~dp0..\.."

echo.
echo  ===============================================
echo    NexaUI - Node.js Server Installation
echo  ===============================================
echo.

REM Check if Node.js is installed
where node >nul 2>&1
if errorlevel 1 (
    echo  [ERROR] Node.js tidak ditemukan!
    echo.
    echo  Silakan install Node.js terlebih dahulu:
    echo  Download: https://nodejs.org/
    echo.
    echo  Setelah install, restart terminal dan jalankan lagi:
    echo    nxdom install node
    echo.
    exit /b 1
)

echo  [1/4] Checking Node.js installation...
call node --version
call npm --version
echo.

REM Check if files already exist
set "FILES_EXIST=0"
if exist "server.js" (
    set "FILES_EXIST=1"
)
if exist "package.json" (
    set "FILES_EXIST=1"
)

if !FILES_EXIST! EQU 1 (
    echo  [WARNING] File server.js atau package.json sudah ada!
    echo.
    choice /C YN /M "  Apakah Anda ingin menimpa file yang ada"
    if errorlevel 2 (
        echo.
        echo  [CANCELLED] Instalasi dibatalkan.
        echo.
        exit /b 0
    )
    echo.
)

echo  [2/4] Creating package.json...
copy /Y "%~dp0templates\package.json.template" "package.json" >nul 2>&1
if errorlevel 1 (
    echo  [ERROR] Failed to create package.json
    exit /b 1
)
echo  [OK] package.json created
echo.

echo  [3/4] Creating server.js...
copy /Y "%~dp0templates\server.js.template" "server.js" >nul 2>&1
if errorlevel 1 (
    echo  [ERROR] Failed to create server.js
    exit /b 1
)
echo  [OK] server.js created
echo.

echo  [4/4] Installing Node.js dependencies...
echo.
call npm install
if errorlevel 1 (
    echo.
    echo  [ERROR] Gagal menginstall dependencies!
    echo  Coba jalankan manual: npm install
    echo.
    exit /b 1
)

echo.
echo  ===============================================
echo    Installation Complete!
echo  ===============================================
echo.
echo  File yang dibuat:
echo    [OK] package.json
echo    [OK] server.js
echo    [OK] node_modules/ ^(dependencies^)
echo.
echo  Langkah selanjutnya:
echo    1. Jalankan Node.js server:
echo       nxdom node
echo       nxdom node 3000
echo.
echo    2. Test server:
echo       http://localhost:3000/api/health
echo.
echo    3. Akses PHP API via proxy:
echo       http://localhost:3000/nx/test
echo.
echo  Dokumentasi lengkap: API_PROXY.md
echo.
endlocal
exit /b 0
