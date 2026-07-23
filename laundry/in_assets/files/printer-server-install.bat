@echo off
setlocal EnableExtensions EnableDelayedExpansion

set "REPO_URL=https://github.com/loehur/printer_server.git"
set "BRANCH=main"

REM Prefer D:\printer_server; fallback C:\printer_server
if exist "D:\" (
  set "TARGET=D:\printer_server"
) else (
  set "TARGET=C:\printer_server"
  echo [INFO] Drive D: tidak ada, install ke C:\printer_server
)

echo ========================================
echo  Print Server - INSTALL
echo  !REPO_URL!
echo  Target: !TARGET!
echo ========================================
echo.

REM --- Cek Git ---
where git >nul 2>&1
if errorlevel 1 (
  echo [ERROR] Git tidak ditemukan di PATH.
  echo Install Git for Windows, lalu jalankan file ini lagi.
  goto :fail
)
echo [OK] Git ditemukan.
git --version
if errorlevel 1 goto :fail

REM --- Cek Node / npm ---
where node >nul 2>&1
if errorlevel 1 (
  echo [ERROR] Node.js tidak ditemukan di PATH.
  echo Install Node.js LTS, lalu jalankan file ini lagi.
  goto :fail
)
where npm >nul 2>&1
if errorlevel 1 (
  echo [ERROR] npm tidak ditemukan di PATH.
  echo Install Node.js LTS ^(termasuk npm^), lalu jalankan file ini lagi.
  goto :fail
)
echo [OK] Node.js ditemukan.
call node --version
call npm --version
echo.

REM --- Clone / pull ---
if not exist "!TARGET!\.git" (
  if exist "!TARGET!" (
    echo [ERROR] Folder sudah ada tapi bukan git repo:
    echo   !TARGET!
    echo Hapus atau pindahkan folder tersebut, lalu jalankan lagi.
    goto :fail
  )
  echo Setup pertama: cloning ke !TARGET! ...
  git clone -b "!BRANCH!" "!REPO_URL!" "!TARGET!"
  if errorlevel 1 (
    echo [ERROR] Gagal clone dari !REPO_URL!
    echo Pastikan internet aktif dan GitHub dapat diakses.
    goto :fail
  )
  echo [OK] Repository di-clone.
) else (
  pushd "!TARGET!"
  if errorlevel 1 (
    echo [ERROR] Tidak bisa masuk folder: !TARGET!
    goto :fail
  )
  echo Memastikan remote origin = !REPO_URL!
  git remote set-url origin "!REPO_URL!"
  echo Pulling latest updates from GitHub...
  git pull --ff-only origin !BRANCH!
  if errorlevel 1 (
    echo.
    echo [WARN] git pull gagal ^(ada perubahan lokal / konflik^).
    echo Mencoba fetch + reset ke origin/!BRANCH! ...
    git fetch origin "!BRANCH!"
    if errorlevel 1 (
      echo [ERROR] Gagal fetch dari GitHub.
      popd
      goto :fail
    )
    if exist "config.local.js" copy /Y "config.local.js" "config.local.js.bak" >nul
    git reset --hard "origin/!BRANCH!"
    if exist "config.local.js.bak" (
      copy /Y "config.local.js.bak" "config.local.js" >nul
      del "config.local.js.bak" >nul
    )
    echo [OK] Update paksa dari GitHub selesai. config.local.js dipertahankan.
  ) else (
    echo [OK] Pull berhasil.
  )
  popd
)
echo.

pushd "!TARGET!"
if errorlevel 1 (
  echo [ERROR] Folder target tidak ditemukan: !TARGET!
  goto :fail
)

REM --- Config lokal ---
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

REM --- npm install ---
echo Menjalankan npm install di !TARGET! ...
call npm install
if errorlevel 1 (
  echo [ERROR] npm install gagal.
  popd
  goto :fail
)
popd

echo [OK] npm install selesai.
echo.
echo ========================================
echo  Install selesai.
echo  Jalankan: !TARGET!\START.bat
echo ========================================
pause
exit /b 0

:fail
echo.
echo ========================================
echo  Install GAGAL. Lihat pesan error di atas.
echo ========================================
pause
exit /b 1
