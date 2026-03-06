@echo off
cd /d "%~dp0"

start "DROMIC-IS Server" powershell -ExecutionPolicy Bypass -File "%~dp0.dev\server.ps1"
timeout /t 2 /nobreak >nul
start "DROMIC-IS Queue" powershell -ExecutionPolicy Bypass -File "%~dp0.dev\queue.ps1"
start "DROMIC-IS Vite" powershell -ExecutionPolicy Bypass -File "%~dp0.dev\vite.ps1"
start "DROMIC-IS Reverb" powershell -ExecutionPolicy Bypass -File "%~dp0.dev\reverb.ps1"
