@echo off
title DROMIC-IS Server
cd /d "C:\Users\DSWDSRV-CARAGA\Desktop\dromic-is"
"C:\Users\DSWDSRV-CARAGA\.config\herd\bin\php84\php.exe" artisan serve --host=0.0.0.0 --port=8000
pause
