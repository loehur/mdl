#!/usr/bin/env bash
# Library sistem untuk Chrome Puppeteer di VPS Linux (headless).
# Jalankan sebagai root:
#   bash scripts/install-chrome-deps.sh
set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Jalankan sebagai root: sudo bash $0"
  exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
  echo "[deps] apt-get tidak ada — install libnspr4 libnss3 manual sesuai distro."
  exit 1
fi

echo "[deps] Update apt…"
apt-get update -qq

# Puppeteer CLI (jika tersedia)
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
if [[ -f "$APP_DIR/node_modules/puppeteer/package.json" ]]; then
  echo "[deps] Coba puppeteer browsers install chrome --install-deps…"
  if (cd "$APP_DIR" && npx puppeteer browsers install chrome --install-deps 2>/dev/null); then
    echo "[deps] Dependency via Puppeteer selesai."
    exit 0
  fi
fi

echo "[deps] Install paket Chrome headless (Debian/Ubuntu)…"
DEBIAN_FRONTEND=noninteractive apt-get install -y \
  ca-certificates fonts-liberation \
  libasound2 libasound2t64 2>/dev/null || true
apt-get install -y \
  ca-certificates fonts-liberation \
  libasound2 libatk-bridge2.0-0 libatk1.0-0 libc6 libcairo2 libcups2 \
  libdbus-1-3 libexpat1 libfontconfig1 libgbm1 libgcc-s1 libglib2.0-0 \
  libgtk-3-0 libnspr4 libnss3 libpango-1.0-0 libpangocairo-1.0-0 \
  libstdc++6 libx11-6 libx11-xcb1 libxcb1 libxcomposite1 libxcursor1 \
  libxdamage1 libxext6 libxfixes3 libxi6 libxrandr2 libxrender1 libxss1 \
  libxtst6 wget xdg-utils \
  2>/dev/null || apt-get install -y \
  ca-certificates fonts-liberation libnspr4 libnss3 libgbm1 libasound2 \
  libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 \
  libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libpango-1.0-0 libcairo2

echo "[deps] Selesai. Restart bca_scrapper, lalu tes scrape QRIS."
