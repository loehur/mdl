@echo off

set "REPO_URL=https://github.com/loehur/printer_server.git"
set "BRANCH=main"

REM Prefer D:\printer_server; fallback ke C:\printer_server
if exist "D:\" (
  set "TARGET=D:\printer_server"
) else (
  set "TARGET=C:\printer_server"
  echo [INFO] Drive D: tidak ada, install ke C:\printer_server
)

echo ========================================
echo  Print Server - INSTALL
echo  %REPO_URL%
echo  Target: %TARGET%
echo ========================================
echo.

REM --- 2. Cek Git ---
where git >nul 2>&1
if errorlevel 1 (
  echo [ERROR] Git tidak ditemukan di PATH.
  echo Install Git for Windows, lalu jalankan INSTALL.bat lagi.
  pause
  exit /b 1
)
echo [OK] Git ditemukan.
git --version

REM --- 3. Cek Node / npm ---
where node >nul 2>&1
if errorlevel 1 (
  echo [ERROR] Node.js tidak ditemukan di PATH.
  echo Install Node.js LTS, lalu jalankan INSTALL.bat lagi.
  pause
  exit /b 1
)
where npm >nul 2>&1
if errorlevel 1 (
  echo [ERROR] npm tidak ditemukan di PATH.
  echo Install Node.js LTS ^(termasuk npm^), lalu jalankan INSTALL.bat lagi.
  pause
  exit /b 1
)
echo [OK] Node.js ditemukan.
node --version
npm --version
echo.

REM --- 4. Clone / pull ke D:\printer_server ---
if not exist "%TARGET%\.git" (
  if exist "%TARGET%" (
    echo [ERROR] Folder sudah ada tapi bukan git repo:
    echo   %TARGET%
    echo Hapus atau pindahkan folder tersebut, lalu jalankan INSTALL.bat lagi.
    pause
    exit /b 1
  )
  echo Setup pertama: cloning ke %TARGET% ...
  git clone -b "%BRANCH%" "%REPO_URL%" "%TARGET%"
  if errorlevel 1 (
    echo [ERROR] Gagal clone dari %REPO_URL%
    pause
    exit /b 1
  )
  echo [OK] Repository di-clone.
) else (
  cd /d "%TARGET%"
  echo Memastikan remote origin = %REPO_URL%
  git remote set-url origin "%REPO_URL%"
  echo Pulling latest updates from GitHub...
  git pull --ff-only origin %BRANCH%
  if errorlevel 1 (
    echo.
    echo [WARN] git pull gagal ^(ada perubahan lokal / konflik^).
    echo Mencoba fetch + reset ke origin/%BRANCH% ...
    git fetch origin "%BRANCH%"
    if errorlevel 1 (
      echo [ERROR] Gagal fetch dari GitHub.
      pause
      exit /b 1
    )
    if exist "config.local.js" copy /Y "config.local.js" "config.local.js.bak" >nul
    git reset --hard "origin/%BRANCH%"
    if exist "config.local.js.bak" (
      copy /Y "config.local.js.bak" "config.local.js" >nul
      del "config.local.js.bak" >nul
    )
    echo [OK] Update paksa dari GitHub selesai. config.local.js dipertahankan.
  ) else (
    echo [OK] Pull berhasil.
  )
)
echo.

cd /d "%TARGET%"

REM --- 5. Config lokal ---
if not exist "config.local.js" (
  if exist "config.local.example.js" (
    echo Membuat config.local.js dari template...
    copy "config.local.example.js" "config.local.js" >nul
    echo [OK] config.local.js dibuat. Edit COM port sesuai printer Anda.
  ) else (
    echo [WARN] config.local.example.js tidak ditemukan.
  )
) else (
  echo [OK] config.local.js sudah ada.
)
echo.

REM --- 6. npm install ---
echo Menjalankan npm install...
call npm install
if errorlevel 1 (
  echo [ERROR] npm install gagal.
  pause
  exit /b 1
)
echo [OK] npm install selesai.
echo.
echo ========================================
echo  Install selesai.
echo  Jalankan: %TARGET%\START.bat
echo ========================================
pause
exit /b 0
