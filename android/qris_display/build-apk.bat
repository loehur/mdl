@echo off
setlocal
cd /d "%~dp0"
call gradlew.bat assembleRelease
if errorlevel 1 exit /b 1
echo.
echo APK: app\build\outputs\apk\release\
dir /b app\build\outputs\apk\release\*.apk 2>nul
