@echo off
cd /d "%~dp0"

set "APK_SRC=app\build\outputs\apk\debug\app-debug.apk"
set "APK_DST=..\..\laundry\in_assets\files\print-bridge.apk"

echo ========================================
echo  Sync Print Bridge APK
echo ========================================
echo.

if not exist "%APK_SRC%" (
  echo APK belum ada. Building debug APK...
  call gradlew.bat assembleDebug --no-daemon
  if errorlevel 1 (
    echo [ERROR] Build gagal.
    pause
    exit /b 1
  )
)

if not exist "%APK_SRC%" (
  echo [ERROR] File tidak ditemukan: %APK_SRC%
  pause
  exit /b 1
)

if not exist "..\..\laundry\in_assets\files" (
  mkdir "..\..\laundry\in_assets\files"
)

copy /Y "%APK_SRC%" "%APK_DST%" >nul
if errorlevel 1 (
  echo [ERROR] Gagal copy ke laundry\in_assets\files\
  pause
  exit /b 1
)

for %%A in ("%APK_DST%") do set SIZE=%%~zA
echo [OK] Sync selesai.
echo   Dari : %CD%\%APK_SRC%
echo   Ke   : %CD%\%APK_DST%
echo   Size : %SIZE% bytes
echo.
pause
exit /b 0
