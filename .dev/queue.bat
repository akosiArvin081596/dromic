@echo off
title DROMIC-IS Queue
cd /d "C:\Users\DSWDSRV-CARAGA\Desktop\dromic-is"
"C:\Users\DSWDSRV-CARAGA\.config\herd\bin\php84\php.exe" artisan queue:listen --tries=1
pause
