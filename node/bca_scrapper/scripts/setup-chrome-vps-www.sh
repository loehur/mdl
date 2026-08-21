#!/usr/bin/env bash
# Install Chrome Puppeteer untuk user www (aaPanel). Fallback: Chromium system.
# Jalankan sebagai root:
#   bash scripts/setup-chrome-vps-www.sh
set -euo pipefail

APP_DIR="${1:-/www/wwwroot/mdl/node/bca_scrapper}"
WWW_USER="${2:-www}"
CACHE_DIR="/home/${WWW_USER}/.cache/puppeteer"
ENV_FILE="${APP_DIR}/.env"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Jalankan sebagai root: sudo bash $0"
  exit 1
fi

if [[ ! -d "$APP_DIR" ]]; then
  echo "Folder tidak ada: $APP_DIR"
  exit 1
fi

echo "[setup] Bersihkan cache Chrome corrupt (jika ada)…"
rm -rf "${CACHE_DIR}/chrome"

echo "[setup] Install Chrome untuk user ${WWW_USER}…"
if ! su -s /bin/bash "$WWW_USER" -c "cd '$APP_DIR' && PUPPETEER_CACHE_DIR='$CACHE_DIR' npm run install:chrome"; then
  echo "[setup] Puppeteer Chrome gagal — coba Chromium system…"
  if command -v apt-get >/dev/null 2>&1; then
    apt-get update -qq
    apt-get install -y chromium-browser 2>/dev/null || apt-get install -y chromium 2>/dev/null || true
  fi
  CHROMIUM=""
  for candidate in /usr/bin/chromium-browser /usr/bin/chromium /snap/bin/chromium; do
    if [[ -x "$candidate" ]]; then
      CHROMIUM="$candidate"
      break
    fi
  done
  if [[ -z "$CHROMIUM" ]]; then
    echo "[setup] ERROR: Chromium system tidak ditemukan. Install manual lalu set PUPPETEER_EXECUTABLE_PATH."
    exit 1
  fi
  echo "[setup] Pakai system Chromium: $CHROMIUM"
  sed -i '/^PUPPETEER_EXECUTABLE_PATH=\/root\//d' "$ENV_FILE"
  sed -i '/^PUPPETEER_EXECUTABLE_PATH=/d' "$ENV_FILE"
  echo "PUPPETEER_EXECUTABLE_PATH=${CHROMIUM}" >> "$ENV_FILE"
  grep -q '^PUPPETEER_CACHE_DIR=' "$ENV_FILE" && sed -i '/^PUPPETEER_CACHE_DIR=/d' "$ENV_FILE"
  echo "[setup] .env → PUPPETEER_EXECUTABLE_PATH=${CHROMIUM}"
  echo "[setup] Restart bca_scrapper di aaPanel, lalu: curl http://127.0.0.1:3021/health"
  exit 0
fi

CHROME="$(su -s /bin/bash "$WWW_USER" -c "cd '$APP_DIR' && node -e \"require('puppeteer').executablePath().then(p=>console.log(p)).catch(()=>process.exit(1))\"")"
echo "[setup] Chrome: $CHROME"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "[setup] .env tidak ditemukan: $ENV_FILE"
  exit 1
fi

sed -i '/^PUPPETEER_EXECUTABLE_PATH=\/root\//d' "$ENV_FILE"
sed -i '/^PUPPETEER_EXECUTABLE_PATH=/d' "$ENV_FILE"
grep -q '^PUPPETEER_CACHE_DIR=' "$ENV_FILE" \
  && sed -i "s|^PUPPETEER_CACHE_DIR=.*|PUPPETEER_CACHE_DIR=${CACHE_DIR}|" "$ENV_FILE" \
  || echo "PUPPETEER_CACHE_DIR=${CACHE_DIR}" >> "$ENV_FILE"

echo "[setup] .env → PUPPETEER_CACHE_DIR=${CACHE_DIR} (tanpa path /root/)"
echo "[setup] Restart bca_scrapper di aaPanel, lalu: curl http://127.0.0.1:3021/health"
