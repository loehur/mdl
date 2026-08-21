#!/usr/bin/env node
/**
 * Install Chrome untuk Puppeteer (wajib di VPS Linux headless).
 * Dipanggil otomatis via npm postinstall, atau manual: npm run install:chrome
 */

const { spawnSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const cacheDir = String(process.env.PUPPETEER_CACHE_DIR || '').trim()
  || path.join(os.homedir(), '.cache', 'puppeteer');

const env = { ...process.env, PUPPETEER_CACHE_DIR: cacheDir };
const cli = require.resolve('puppeteer/lib/cjs/puppeteer/node/cli.js');
const args = ['browsers', 'install', 'chrome'];
const cwd = path.join(__dirname, '..');

function chromeCacheDir() {
  return path.join(cacheDir, 'chrome');
}

function clearChromeCache(label) {
  const dir = chromeCacheDir();
  if (!fs.existsSync(dir)) {
    return;
  }
  console.log(`[bca_scrapper] ${label}: hapus cache corrupt ${dir}`);
  fs.rmSync(dir, { recursive: true, force: true });
}

function runInstall(attempt) {
  console.log(`[bca_scrapper] Installing Puppeteer Chrome (attempt ${attempt})…`);
  console.log('[bca_scrapper] PUPPETEER_CACHE_DIR =', cacheDir);
  return spawnSync(process.execPath, [cli, ...args], {
    cwd,
    env,
    stdio: 'inherit',
  });
}

function printFallbackHelp() {
  console.error(
    '\n[bca_scrapper] Install Chrome gagal (download corrupt / jaringan putus).\n'
      + 'Perbaikan A — bersihkan cache lalu ulangi:\n'
      + `  rm -rf ${chromeCacheDir()}\n`
      + `  PUPPETEER_CACHE_DIR=${cacheDir} npm run install:chrome\n\n`
      + 'Perbaikan B — pakai Chromium system (aaPanel/VPS):\n'
      + '  apt update && apt install -y chromium-browser\n'
      + '  # atau: apt install -y chromium\n'
      + 'Lalu di .env (hapus path /root/):\n'
      + '  PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser\n'
      + '  # comment/hapus PUPPETEER_CACHE_DIR jika pakai system chromium\n'
  );
}

let result = runInstall(1);
if (result.status !== 0) {
  clearChromeCache('Retry');
  result = runInstall(2);
}

if (result.status !== 0) {
  printFallbackHelp();
  process.exit(result.status || 1);
}

console.log('[bca_scrapper] Chrome Puppeteer siap.');
