@echo off
title DROMIC-IS Reverb
cd /d "C:\Users\DSWDSRV-CARAGA\Desktop\dromic-is"
"C:\Users\DSWDSRV-CARAGA\.config\herd\bin\php84\php.exe" artisan reverb:start --debug
pause
