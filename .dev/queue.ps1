$Host.UI.RawUI.WindowTitle = "DROMIC-IS Queue"
Set-Location "C:\Users\DSWDSRV-CARAGA\Desktop\dromic-is"
php artisan queue:listen --tries=1
Read-Host "Press Enter to exit"
