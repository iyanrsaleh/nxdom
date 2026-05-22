@echo off
setlocal enabledelayedexpansion

set "DIR=%~dp0"

if "%~1"=="" (
  echo.
  echo  NexaKit HTML ^-^> Fluent API Converter
  echo  ────────────────────────────────────────
  echo  Usage   : NexaKit.bat ^<input.html^>
  echo  Example : NexaKit.bat index.html
  echo.
  echo  Output  : kit_^<name^>.js  ^(same folder as input^)
  echo  Example : kit_index.js
  echo.
  exit /b 0
)

:: Check Node.js is available
where node >nul 2>&1
if errorlevel 1 (
  echo [ERROR] Node.js not found. Please install Node.js first.
  exit /b 1
)

:: Check converter script exists
set "CONVERTER=%DIR%NexaKit_convert.js"
if not exist "%CONVERTER%" (
  echo [ERROR] NexaKit_convert.js not found in: %DIR%
  exit /b 1
)

:: Run converter — pass all arguments through
node "%CONVERTER%" %*
exit /b %errorlevel%
