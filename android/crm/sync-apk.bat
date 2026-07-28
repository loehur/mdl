@echo off
cd /d "%~dp0"

set "APK_SRC=app\build\outputs\apk\debug\app-debug.apk"
set "APK_DST=..\..\laundry\in_assets\files\mdl-chat.apk"

echo ========================================
echo  Sync MDL Chat APK
echo ========================================
echo.

if not exist "%APK_SRC%" (
  echo APK belum ada. Building debug APK...
  if defined JAVA_HOME (
    echo Using JAVA_HOME=%JAVA_HOME%
  ) else if exist "C:\Program Files\Android\Android Studio\jbr\bin\java.exe" (
    set "JAVA_HOME=C:\Program Files\Android\Android Studio\jbr"
    echo Using Android Studio JBR
  )
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
