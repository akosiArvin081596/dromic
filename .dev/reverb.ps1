$Host.UI.RawUI.WindowTitle = "DROMIC-IS Reverb"
Set-Location "C:\Users\DSWDSRV-CARAGA\Desktop\dromic-is"
php artisan reverb:start --debug
Read-Host "Press Enter to exit"
