@echo off
setlocal EnableExtensions
title Windows Kasir Debloat - Full

:: ============================================================
:: SELF-ELEVATE TO ADMIN
:: ============================================================
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Meminta hak Administrator...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

cls
echo ============================================================
echo           WINDOWS KASIR DEBLOAT - FULL
echo ============================================================
echo.
echo Printer / USB / Network / Defender / Windows Update
echo tetap dipertahankan.
echo.
echo Tekan tombol apa saja untuk mulai...
pause >nul

:: ============================================================
:: RUN POWERSHELL DEBLOAT
:: ============================================================

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
"$ErrorActionPreference='SilentlyContinue';" ^
"Write-Host '1. Menghapus aplikasi consumer...' -ForegroundColor Cyan;" ^
"$apps=@('*Clipchamp*','*Microsoft.BingNews*','*Microsoft.BingWeather*','*Microsoft.GamingApp*','*Microsoft.GetHelp*','*Microsoft.Getstarted*','*Microsoft.MicrosoftSolitaireCollection*','*Microsoft.People*','*Microsoft.WindowsFeedbackHub*','*Microsoft.Xbox*','*Microsoft.ZuneMusic*','*Microsoft.ZuneVideo*','*MicrosoftTeams*');" ^
"foreach($app in $apps){Get-AppxPackage -AllUsers $app | Remove-AppxPackage -AllUsers};" ^
"" ^
"Write-Host '2. Mematikan widget, tips, promosi, rekomendasi...' -ForegroundColor Cyan;" ^
"reg add 'HKLM\SOFTWARE\Policies\Microsoft\Dsh' /v AllowNewsAndInterests /t REG_DWORD /d 0 /f | Out-Null;" ^
"reg add 'HKLM\SOFTWARE\Policies\Microsoft\Windows\CloudContent' /v DisableWindowsConsumerFeatures /t REG_DWORD /d 1 /f | Out-Null;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\ContentDeliveryManager' /v SoftLandingEnabled /t REG_DWORD /d 0 /f | Out-Null;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\ContentDeliveryManager' /v SystemPaneSuggestionsEnabled /t REG_DWORD /d 0 /f | Out-Null;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\Explorer\Advanced' /v Start_IrisRecommendations /t REG_DWORD /d 0 /f | Out-Null;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\AdvertisingInfo' /v Enabled /t REG_DWORD /d 0 /f | Out-Null;" ^
"" ^
"Write-Host '3. Mematikan Game DVR dan Xbox services...' -ForegroundColor Cyan;" ^
"reg add 'HKCU\System\GameConfigStore' /v GameDVR_Enabled /t REG_DWORD /d 0 /f | Out-Null;" ^
"reg add 'HKLM\SOFTWARE\Policies\Microsoft\Windows\GameDVR' /v AllowGameDVR /t REG_DWORD /d 0 /f | Out-Null;" ^
"$xbox=@('XblAuthManager','XblGameSave','XboxNetApiSvc','XboxGipSvc');" ^
"foreach($s in $xbox){Stop-Service $s -Force; Set-Service $s -StartupType Disabled};" ^
"" ^
"Write-Host '4. Mematikan Windows Search indexing...' -ForegroundColor Cyan;" ^
"Stop-Service WSearch -Force; Set-Service WSearch -StartupType Disabled;" ^
"" ^
"Write-Host '5. Mengurangi telemetry...' -ForegroundColor Cyan;" ^
"$telemetry=@('DiagTrack','dmwappushservice');" ^
"foreach($s in $telemetry){Stop-Service $s -Force; Set-Service $s -StartupType Disabled};" ^
"" ^
"Write-Host '6. Mematikan Delivery Optimization P2P...' -ForegroundColor Cyan;" ^
"reg add 'HKLM\SOFTWARE\Policies\Microsoft\Windows\DeliveryOptimization' /v DODownloadMode /t REG_DWORD /d 0 /f | Out-Null;" ^
"" ^
"Write-Host '7. Membatasi background apps...' -ForegroundColor Cyan;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\BackgroundAccessApplications' /v GlobalUserDisabled /t REG_DWORD /d 1 /f | Out-Null;" ^
"" ^
"Write-Host '8. Mematikan activity history dan recent docs...' -ForegroundColor Cyan;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer' /v NoRecentDocsHistory /t REG_DWORD /d 1 /f | Out-Null;" ^
"reg add 'HKLM\SOFTWARE\Policies\Microsoft\Windows\System' /v EnableActivityFeed /t REG_DWORD /d 0 /f | Out-Null;" ^
"reg add 'HKLM\SOFTWARE\Policies\Microsoft\Windows\System' /v PublishUserActivities /t REG_DWORD /d 0 /f | Out-Null;" ^
"reg add 'HKLM\SOFTWARE\Policies\Microsoft\Windows\System' /v UploadUserActivities /t REG_DWORD /d 0 /f | Out-Null;" ^
"" ^
"Write-Host '9. Mengurangi visual effects...' -ForegroundColor Cyan;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\Themes\Personalize' /v EnableTransparency /t REG_DWORD /d 0 /f | Out-Null;" ^
"reg add 'HKCU\Control Panel\Desktop\WindowMetrics' /v MinAnimate /t REG_SZ /d 0 /f | Out-Null;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\Explorer\VisualEffects' /v VisualFXSetting /t REG_DWORD /d 2 /f | Out-Null;" ^
"reg add 'HKCU\Control Panel\Desktop' /v MenuShowDelay /t REG_SZ /d 100 /f | Out-Null;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\Explorer\Advanced' /v TaskbarAnimations /t REG_DWORD /d 0 /f | Out-Null;" ^
"" ^
"Write-Host '10. Mematikan service non-esensial PC kasir...' -ForegroundColor Cyan;" ^
"$optional=@('WerSvc','MapsBroker','RemoteRegistry','RetailDemo','TabletInputService','Fax','wisvc','PhoneSvc','lfsvc','CDPSvc');" ^
"foreach($s in $optional){Stop-Service $s -Force; Set-Service $s -StartupType Disabled};" ^
"" ^
"Write-Host '11. Mematikan AutoPlay...' -ForegroundColor Cyan;" ^
"reg add 'HKCU\Software\Microsoft\Windows\CurrentVersion\Explorer\AutoplayHandlers' /v DisableAutoplay /t REG_DWORD /d 1 /f | Out-Null;" ^
"" ^
"Write-Host '12. Mematikan hibernation dan Fast Startup...' -ForegroundColor Cyan;" ^
"powercfg /hibernate off;" ^
"reg add 'HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Power' /v HiberbootEnabled /t REG_DWORD /d 0 /f | Out-Null;" ^
"" ^
"Write-Host '13. Mematikan scheduled task consumer/telemetry...' -ForegroundColor Cyan;" ^
"$tasks=@('\Microsoft\Windows\Application Experience\Microsoft Compatibility Appraiser','\Microsoft\Windows\Customer Experience Improvement Program\Consolidator','\Microsoft\Windows\Customer Experience Improvement Program\UsbCeip','\Microsoft\Windows\Feedback\Siuf\DmClient','\Microsoft\Windows\Feedback\Siuf\DmClientOnScenarioDownload');" ^
"foreach($t in $tasks){schtasks /Change /TN $t /Disable 2>$null | Out-Null};" ^
"" ^
"Write-Host '14. Membersihkan temporary files...' -ForegroundColor Cyan;" ^
"Remove-Item ($env:TEMP + '\*') -Recurse -Force;" ^
"Remove-Item 'C:\Windows\Temp\*' -Recurse -Force;" ^
"" ^
"Write-Host '';" ^
"Write-Host '============================================================' -ForegroundColor Green;" ^
"Write-Host 'WINDOWS KASIR DEBLOAT SELESAI' -ForegroundColor Green;" ^
"Write-Host '============================================================' -ForegroundColor Green;" ^
"Write-Host '';" ^
"Write-Host 'Tetap dipertahankan:' -ForegroundColor Yellow;" ^
"Write-Host ' + Print Spooler';" ^
"Write-Host ' + USB / Plug and Play';" ^
"Write-Host ' + LAN / Wi-Fi / Internet';" ^
"Write-Host ' + Windows Defender';" ^
"Write-Host ' + Windows Update';" ^
"Write-Host ' + Microsoft Store';" ^
"Write-Host ' + Microsoft Edge';" ^
"Write-Host ' + Audio';" ^
"Write-Host ' + Node.js / aplikasi kasir';"

echo.
echo ============================================================
echo SELESAI
echo ============================================================
echo.
echo Disarankan restart Windows.
echo.
choice /C YN /N /M "Restart sekarang? [Y/N]: "
if errorlevel 2 goto END
if errorlevel 1 shutdown /r /t 0

:END
echo.
echo Tekan tombol apa saja untuk keluar...
pause >nul
endlocal
