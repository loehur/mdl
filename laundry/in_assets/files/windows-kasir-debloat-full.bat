@echo off
setlocal EnableExtensions
title Windows Kasir Debloat - Full V2

:: =========================
:: SELF ELEVATE
:: =========================
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Meminta hak Administrator...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

cls
echo ============================================================
echo       WINDOWS KASIR DEBLOAT - FULL V2
echo ============================================================
echo.
echo Aman untuk Chrome, Node.js printer server, Print Spooler,
echo USB printer, LAN/Wi-Fi, Defender, Windows Update, Store, Edge.
echo.
echo Windows Search TIDAK dimatikan pada versi ini.
echo.
echo Tekan tombol apa saja untuk mulai...
pause >nul

set "PS1=%TEMP%\windows-kasir-debloat-%RANDOM%.ps1"

powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
  "$c = Get-Content -LiteralPath '%~f0';" ^
  "$i = [Array]::IndexOf($c,'### POWERSHELL_START ###');" ^
  "if($i -lt 0){exit 90};" ^
  "$c[($i+1)..($c.Length-1)] | Set-Content -LiteralPath '%PS1%' -Encoding UTF8"

if not exist "%PS1%" (
    echo.
    echo GAGAL membuat script sementara.
    echo.
    pause
    exit /b 1
)

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%PS1%"
set "RC=%ERRORLEVEL%"

del /q "%PS1%" >nul 2>&1

echo.
echo ============================================================
if "%RC%"=="0" (
    echo SELESAI.
) else (
    echo Script selesai dengan kode error: %RC%
)
echo ============================================================
echo.
choice /C YN /N /M "Restart sekarang? [Y/N]: "
if errorlevel 2 goto END
if errorlevel 1 shutdown /r /t 0

:END
echo.
echo Tekan tombol apa saja untuk keluar...
pause >nul
exit /b

### POWERSHELL_START ###
$ErrorActionPreference = "SilentlyContinue"

function Section($title) {
    Write-Host ""
    Write-Host "==================================================" -ForegroundColor DarkGray
    Write-Host $title -ForegroundColor Cyan
    Write-Host "==================================================" -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "WINDOWS KASIR DEBLOAT - FULL V2" -ForegroundColor Green
Write-Host "Windows Search tetap aktif." -ForegroundColor Yellow

Section "1. Menghapus aplikasi consumer"

$apps = @(
    "*Clipchamp*",
    "*Microsoft.BingNews*",
    "*Microsoft.BingWeather*",
    "*Microsoft.GamingApp*",
    "*Microsoft.GetHelp*",
    "*Microsoft.Getstarted*",
    "*Microsoft.MicrosoftSolitaireCollection*",
    "*Microsoft.People*",
    "*Microsoft.WindowsFeedbackHub*",
    "*Microsoft.Xbox*",
    "*Microsoft.ZuneMusic*",
    "*Microsoft.ZuneVideo*",
    "*MicrosoftTeams*"
)

foreach ($app in $apps) {
    Write-Host "Remove: $app"
    Get-AppxPackage -AllUsers $app | Remove-AppxPackage -AllUsers
}

Section "2. Mematikan widget, tips, promosi, rekomendasi"

reg add "HKLM\SOFTWARE\Policies\Microsoft\Dsh" /v AllowNewsAndInterests /t REG_DWORD /d 0 /f | Out-Null
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\CloudContent" /v DisableWindowsConsumerFeatures /t REG_DWORD /d 1 /f | Out-Null
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\ContentDeliveryManager" /v SoftLandingEnabled /t REG_DWORD /d 0 /f | Out-Null
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\ContentDeliveryManager" /v SystemPaneSuggestionsEnabled /t REG_DWORD /d 0 /f | Out-Null
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Explorer\Advanced" /v Start_IrisRecommendations /t REG_DWORD /d 0 /f | Out-Null
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\AdvertisingInfo" /v Enabled /t REG_DWORD /d 0 /f | Out-Null

Section "3. Mematikan Game DVR dan Xbox services"

reg add "HKCU\System\GameConfigStore" /v GameDVR_Enabled /t REG_DWORD /d 0 /f | Out-Null
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\GameDVR" /v AllowGameDVR /t REG_DWORD /d 0 /f | Out-Null

$xboxServices = @("XblAuthManager","XblGameSave","XboxNetApiSvc","XboxGipSvc")
foreach ($service in $xboxServices) {
    Write-Host "Disable service: $service"
    Stop-Service $service -Force
    Set-Service $service -StartupType Disabled
}

Section "4. Mengurangi telemetry"

$telemetryServices = @("DiagTrack","dmwappushservice")
foreach ($service in $telemetryServices) {
    Write-Host "Disable service: $service"
    Stop-Service $service -Force
    Set-Service $service -StartupType Disabled
}

Section "5. Mematikan Delivery Optimization P2P"

reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\DeliveryOptimization" /v DODownloadMode /t REG_DWORD /d 0 /f | Out-Null

Section "6. Membatasi background apps consumer"

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\BackgroundAccessApplications" /v GlobalUserDisabled /t REG_DWORD /d 1 /f | Out-Null

Section "7. Mematikan activity history dan recent docs"

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer" /v NoRecentDocsHistory /t REG_DWORD /d 1 /f | Out-Null
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\System" /v EnableActivityFeed /t REG_DWORD /d 0 /f | Out-Null
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\System" /v PublishUserActivities /t REG_DWORD /d 0 /f | Out-Null
reg add "HKLM\SOFTWARE\Policies\Microsoft\Windows\System" /v UploadUserActivities /t REG_DWORD /d 0 /f | Out-Null

Section "8. Mengurangi visual effects"

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Themes\Personalize" /v EnableTransparency /t REG_DWORD /d 0 /f | Out-Null
reg add "HKCU\Control Panel\Desktop\WindowMetrics" /v MinAnimate /t REG_SZ /d 0 /f | Out-Null
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Explorer\VisualEffects" /v VisualFXSetting /t REG_DWORD /d 2 /f | Out-Null
reg add "HKCU\Control Panel\Desktop" /v MenuShowDelay /t REG_SZ /d 100 /f | Out-Null
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Explorer\Advanced" /v TaskbarAnimations /t REG_DWORD /d 0 /f | Out-Null

Section "9. Mematikan service non-esensial PC kasir"

$optionalServices = @(
    "WerSvc",
    "MapsBroker",
    "RemoteRegistry",
    "RetailDemo",
    "TabletInputService",
    "Fax",
    "wisvc",
    "PhoneSvc",
    "lfsvc",
    "CDPSvc"
)

foreach ($service in $optionalServices) {
    Write-Host "Disable service: $service"
    Stop-Service $service -Force
    Set-Service $service -StartupType Disabled
}

Section "10. Mematikan AutoPlay"

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Explorer\AutoplayHandlers" /v DisableAutoplay /t REG_DWORD /d 1 /f | Out-Null

Section "11. Mematikan hibernation dan Fast Startup"

powercfg /hibernate off
reg add "HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Power" /v HiberbootEnabled /t REG_DWORD /d 0 /f | Out-Null

Section "12. Mematikan scheduled task consumer/telemetry"

$tasks = @(
    "\Microsoft\Windows\Application Experience\Microsoft Compatibility Appraiser",
    "\Microsoft\Windows\Customer Experience Improvement Program\Consolidator",
    "\Microsoft\Windows\Customer Experience Improvement Program\UsbCeip",
    "\Microsoft\Windows\Feedback\Siuf\DmClient",
    "\Microsoft\Windows\Feedback\Siuf\DmClientOnScenarioDownload"
)

foreach ($task in $tasks) {
    Write-Host "Disable task: $task"
    schtasks /Change /TN $task /Disable 2>$null | Out-Null
}

Section "13. Membersihkan temporary files"

Remove-Item "$env:TEMP\*" -Recurse -Force
Remove-Item "C:\Windows\Temp\*" -Recurse -Force

Write-Host ""
Write-Host "==================================================" -ForegroundColor Green
Write-Host "WINDOWS KASIR DEBLOAT SELESAI" -ForegroundColor Green
Write-Host "==================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Tetap aktif:" -ForegroundColor Yellow
Write-Host " + Windows Search / Start Search"
Write-Host " + Print Spooler"
Write-Host " + USB / Plug and Play"
Write-Host " + LAN / Wi-Fi / Internet"
Write-Host " + Windows Defender"
Write-Host " + Windows Update"
Write-Host " + Microsoft Store"
Write-Host " + Microsoft Edge"
Write-Host " + Chrome"
Write-Host " + Audio"
Write-Host " + Node.js / printer server"
Write-Host ""
exit 0
