$Host.UI.RawUI.WindowTitle = "DROMIC-IS Server"
Set-Location "C:\Users\DSWDSRV-CARAGA\Desktop\dromic-is"
php artisan serve --host=10.237.17.234 --port=8000
Read-Host "Press Enter to exit"
