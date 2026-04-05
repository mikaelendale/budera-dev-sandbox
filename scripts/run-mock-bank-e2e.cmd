@echo off
REM Run mock-bank E2E via PowerShell (double-click this file or run from cmd.exe)
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0test-mock-bank-e2e.ps1" %*
exit /b %ERRORLEVEL%
