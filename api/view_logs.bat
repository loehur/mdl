@echo off
REM Quick Log Viewer for Auto-Reply Debugging
REM Run this in PowerShell or CMD

echo ========================================
echo AUTO-REPLY LOG VIEWER
echo ========================================
echo.
echo Pilih log yang mau dilihat:
echo.
echo 1. Process Log (flow utama)
echo 2. Pattern Check (detail pattern)
echo 3. Match Log (pattern yang match)
echo 4. Success Log (handler berhasil)
echo 5. Cooldown Log (rate limit)
echo 6. Error Log (jika ada error)
echo 7. AI Log (AI fallback)
echo 8. ALL LOGS (live monitoring)
echo 9. Clear ALL logs
echo 0. Exit
echo.
set /p choice="Pilih (0-9): "

if "%choice%"=="1" goto process
if "%choice%"=="2" goto pattern
if "%choice%"=="3" goto match
if "%choice%"=="4" goto success
if "%choice%"=="5" goto cooldown
if "%choice%"=="6" goto error
if "%choice%"=="7" goto ai
if "%choice%"=="8" goto all
if "%choice%"=="9" goto clear
if "%choice%"=="0" goto end

:process
echo.
echo ========== PROCESS LOG ==========
type logs\auto_reply_process.log 2>nul || echo [No log yet]
pause
goto menu

:pattern
echo.
echo ========== PATTERN CHECK LOG ==========
type logs\auto_reply_pattern_check.log 2>nul || echo [No log yet]
pause
goto menu

:match
echo.
echo ========== MATCH LOG ==========
type logs\auto_reply_match.log 2>nul || echo [No log yet]
pause
goto menu

:success
echo.
echo ========== SUCCESS LOG ==========
type logs\auto_reply_success.log 2>nul || echo [No log yet]
pause
goto menu

:cooldown
echo.
echo ========== COOLDOWN LOG ==========
type logs\auto_reply_cooldown.log 2>nul || echo [No log yet]
pause
goto menu

:error
echo.
echo ========== ERROR LOG ==========
type logs\auto_reply_error.log 2>nul || echo [No log yet]
pause
goto menu

:ai
echo.
echo ========== AI LOG ==========
type logs\auto_reply_ai.log 2>nul || echo [No log yet]
pause
goto menu

:all
echo.
echo ========== ALL LOGS (Press Ctrl+C to stop) ==========
powershell -Command "Get-Content logs\auto_reply_*.log -Wait -Tail 100 2>$null"
goto menu

:clear
echo.
echo Clearing all auto-reply logs...
del /Q logs\auto_reply_*.log 2>nul
echo.
echo ✅ All logs cleared!
pause
goto menu

:menu
cls
goto start

:end
echo.
echo Bye!
