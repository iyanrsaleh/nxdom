@echo off
REM Dipanggil dari "nxdom dev" — jalankan start-server.bat dengan cwd = root proyek
setlocal EnableDelayedExpansion
cd /d "%~dp0..\.."
call "%~dp0start-server.bat" %*
exit /b %errorlevel%
