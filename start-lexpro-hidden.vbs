Set WshShell = CreateObject("WScript.Shell")
Set WshShellEnv = WshShell.Environment("Process")

' Start Laravel in background
WshShell.CurrentDirectory = "C:\Users\Admin\Documents\law-office"
WshShell.Run "cmd /c php artisan serve --host=0.0.0.0 --port=8000", 0, False

' Start Cloudflare Tunnel in background
WshShell.CurrentDirectory = "C:\Users\Admin\.cloudflared"
WshShell.Run """C:\Program Files (x86)\cloudflared\cloudflared.exe"" tunnel --config ""C:\Users\Admin\.cloudflared\config.yml"" run fa151cf8-673f-43af-9858-b2ba4eaca6e8", 0, False

' Start Laravel Scheduler in background (discord:status every 5min)
WshShell.CurrentDirectory = "C:\Users\Admin\Documents\law-office"
WshShell.Run "cmd /c php artisan schedule:work", 0, False
