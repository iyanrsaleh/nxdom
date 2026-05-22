@echo off
setlocal enabledelayedexpansion
REM ================================================================================
REM NexaUI Framework - PHP CLI Server Starter
REM Lokasi: system/bin/start-server.bat — cwd = root proyek (dua tingkat di atas)
REM ================================================================================

REM Root proyek (www)
cd /d "%~dp0..\.."

echo.
echo ================================================================================
echo   NexaUI Framework - Starting PHP CLI Server
echo ================================================================================
echo.
echo [USAGE] start-server.bat [PORT] [1^|2]
echo        Contoh: start-server.bat 3000
echo        Argumen ke-2 opsional: 1=localhost saja, 2=jaringan ^(tanpa prompt^)
echo        Jika tidak ada argument, akan diminta input port secara interaktif
echo.

REM Check if PHP is available
php -v >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP tidak ditemukan!
    echo Pastikan PHP sudah terinstall dan ada di PATH environment.
    echo.
    pause
    exit /b 1
)

REM Check if vendor directory exists
set VENDOR_FOUND=0
if exist "vendor\autoload.php" (
    set VENDOR_FOUND=1
    goto :vendor_found
)
if exist "vendor/autoload.php" (
    set VENDOR_FOUND=1
    goto :vendor_found
)

REM If vendor not found, ask to install
if %VENDOR_FOUND%==0 (
    echo [WARNING] vendor/autoload.php tidak ditemukan!
    echo [INFO] Current directory: %CD%
    echo.
    echo Apakah Anda ingin menjalankan 'composer install' sekarang? (Y/N)
    set /p install="> "
    if /i "%install%"=="Y" (
        echo.
        echo Menjalankan composer install...
        if exist "composer.bat" (
            call composer.bat install
        ) else (
            composer install
        )
        if errorlevel 1 (
            echo [ERROR] Gagal menjalankan composer install!
            echo [INFO] Coba jalankan manual: composer install
            pause
            exit /b 1
        )
        echo.
        echo [SUCCESS] Composer install berhasil!
        echo.
        REM Re-check after install
        if exist "vendor\autoload.php" (
            set VENDOR_FOUND=1
        ) else if exist "vendor/autoload.php" (
            set VENDOR_FOUND=1
        )
    ) else (
        echo.
        echo [INFO] Untuk menginstall dependencies, jalankan: install-dependencies.bat
        echo.
        echo [ERROR] Composer dependencies diperlukan untuk menjalankan aplikasi.
        pause
        exit /b 1
    )
)

:vendor_found
if %VENDOR_FOUND%==1 (
    echo [OK] vendor/autoload.php ditemukan.
    echo.
)

REM Check if .env file exists
if not exist ".env" (
    echo [INFO] File .env tidak ditemukan, menggunakan default configuration.
    echo.
)

REM Display PHP version
echo [INFO] PHP Version:
php -v
echo.

REM Display current directory
echo [INFO] Working Directory: %CD%
echo.

REM Set default port
set PORT=8000
set BIND_HOST=localhost

REM Check if port is provided as argument
if not "%1"=="" (
    set PORT=%1
    goto :port_set
)

REM Ask user for port if not provided
echo [INFO] Port default: 8000
echo [INFO] Tekan Enter untuk menggunakan port default, atau masukkan port yang diinginkan:
set /p PORT="> Port (default 8000): "
if "%PORT%"=="" (
    set PORT=8000
)

:port_set
REM Validate port number (must be between 1 and 65535)
set /a PORT_NUM=%PORT% 2>nul
if errorlevel 1 (
    echo [ERROR] Port tidak valid! Menggunakan port default 8000.
    set PORT=8000
) else (
    if %PORT_NUM% LSS 1 (
        echo [ERROR] Port harus lebih besar dari 0! Menggunakan port default 8000.
        set PORT=8000
    ) else if %PORT_NUM% GTR 65535 (
        echo [ERROR] Port tidak boleh lebih besar dari 65535! Menggunakan port default 8000.
        set PORT=8000
    )
)

REM Ask if user wants to allow access from network (skip prompt if arg 2 = 1 or 2)
if /i "%~2"=="1" (
    set "ACCESS_MODE=1"
    echo.
    echo [INFO] Mode akses: localhost saja ^(argumen ke-2^)
    goto :access_mode_done
)
if /i "%~2"=="2" (
    set "ACCESS_MODE=2"
    echo.
    echo [INFO] Mode akses: jaringan lokal ^(argumen ke-2^)
    goto :access_mode_done
)
echo.
echo [INFO] Mode akses server:
echo        1. Localhost saja (hanya bisa diakses dari komputer ini)
echo        2. Jaringan lokal (bisa diakses dari komputer lain di jaringan)
echo.
set /p ACCESS_MODE="> Pilih mode (1/2, default 1): "
if "%ACCESS_MODE%"=="" set "ACCESS_MODE=1"
:access_mode_done

REM Set BIND_HOST based on mode - use explicit comparison
set BIND_HOST=localhost
set LOCAL_IP=

if /i "%ACCESS_MODE%"=="2" (
    set BIND_HOST=0.0.0.0
    echo.
    echo [INFO] Server akan dapat diakses dari jaringan lokal
    echo [INFO] Mencari IP address komputer ini...
    
    REM Get local IP address - simpler method
    for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i /c:"IPv4"') do (
        set "TEMP_IP=%%a"
        REM Remove leading spaces
        for /f "tokens=* delims= " %%b in ("!TEMP_IP!") do set "TEMP_IP=%%b"
        REM Skip loopback and check if it's a valid IP format (contains dots)
        echo !TEMP_IP! | findstr /r "^[0-9][0-9]*\.[0-9]" >nul
        if !errorlevel! equ 0 (
            if not "!TEMP_IP:~0,3!"=="127" (
                set LOCAL_IP=!TEMP_IP!
                goto :ip_found
            )
        )
    )
    
    :ip_found
    if defined LOCAL_IP (
        echo [SUCCESS] IP address ditemukan: !LOCAL_IP!
        echo [INFO] Akses dari komputer lain menggunakan: http://!LOCAL_IP!:%PORT%
    ) else (
        echo [WARNING] Tidak dapat mendeteksi IP address otomatis
        echo [INFO] Untuk melihat IP address, jalankan: ipconfig
        echo [INFO] Server tetap berjalan di 0.0.0.0:%PORT%
        echo [INFO] Gunakan IP address komputer ini untuk akses dari jaringan
    )
) else (
    echo.
    echo [INFO] Server hanya dapat diakses dari komputer ini
)

REM php -S localhost:port di Windows bisa hanya ::1; proxy Node pakai 127.0.0.1 → ECONNREFUSED
set "PHP_BIND=127.0.0.1"
if "!BIND_HOST!"=="0.0.0.0" set "PHP_BIND=0.0.0.0"

echo.
echo ================================================================================
if "!BIND_HOST!"=="0.0.0.0" (
    echo [INFO] Server Mode: Jaringan Lokal (Network Access)
    echo [INFO] Server akan bind ke semua network interface (0.0.0.0)
    echo [INFO] Dapat diakses dari:
    echo        - http://localhost:%PORT%
    if defined LOCAL_IP (
        echo        - http://!LOCAL_IP!:%PORT%
    )
    echo        - http://[IP-KOMPUTER-INI]:%PORT%
    echo.
    echo [WARNING] Server dapat diakses dari komputer lain di jaringan yang sama!
    echo [INFO] Pastikan firewall mengizinkan koneksi ke port %PORT%
) else (
    echo [INFO] Server Mode: Localhost Only
    echo [INFO] Starting server on http://localhost:%PORT%
)
echo [INFO] Press Ctrl+C to stop the server
echo ================================================================================
echo.

REM Port tersimpan untuk nxdom node (baca di server.js). %CD% = root proyek (sudah cd di atas)
echo !PORT!>"%CD%\.nxdom-php-port"

set "ROUTER=%~dp0router.php"

REM Start PHP CLI Server with router for proper MIME types
if exist "%ROUTER%" (
    echo [INFO] Using system\bin\router.php for proper MIME type handling
    echo [INFO] Starting PHP server: php -S !PHP_BIND!:!PORT! "%ROUTER%"
    echo.
    if "!BIND_HOST!"=="0.0.0.0" (
        echo [DEBUG] Binding to 0.0.0.0:%PORT% - server accessible from network
        echo [DEBUG] Server dapat diakses dari http://localhost:%PORT% atau http://!LOCAL_IP!:%PORT%
    )
    php -S !PHP_BIND!:!PORT! "%ROUTER%"
    if errorlevel 1 (
        echo.
        echo [ERROR] Gagal memulai server!
        if "!BIND_HOST!"=="0.0.0.0" (
            echo [INFO] Coba periksa:
            echo        - Apakah port %PORT% sudah digunakan?
            echo        - Apakah firewall memblokir port %PORT%?
            echo        - Coba jalankan sebagai Administrator
        )
    )
) else (
    echo [INFO] router.php not found in system\bin, using index.php directly
    echo [INFO] Starting PHP server: php -S !PHP_BIND!:!PORT! -t . index.php
    echo.
    if "!BIND_HOST!"=="0.0.0.0" (
        echo [DEBUG] Binding to 0.0.0.0:%PORT% - server accessible from network
        echo [DEBUG] Server dapat diakses dari http://localhost:%PORT% atau http://!LOCAL_IP!:%PORT%
    )
    php -S !PHP_BIND!:!PORT! -t . index.php
    if errorlevel 1 (
        echo.
        echo [ERROR] Gagal memulai server!
        if "!BIND_HOST!"=="0.0.0.0" (
            echo [INFO] Coba periksa:
            echo        - Apakah port %PORT% sudah digunakan?
            echo        - Apakah firewall memblokir port %PORT%?
            echo        - Coba jalankan sebagai Administrator
        )
    )
)

pause
