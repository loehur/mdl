#!/usr/bin/env node
/**
 * Install Chrome untuk Puppeteer (wajib di VPS Linux headless).
 * Dipanggil otomatis via npm postinstall, atau manual: npm run install:chrome
 */

const { spawnSync } = require('child_process');
const path = require('path');

const cacheDir = process.env.PUPPETEER_CACHE_DIR || '';
const env = { ...process.env };
if (cacheDir) {
  env.PUPPETEER_CACHE_DIR = cacheDir;
}

console.log('[bca_scrapper] Installing Puppeteer Chrome…');
if (cacheDir) {
  console.log('[bca_scrapper] PUPPETEER_CACHE_DIR =', cacheDir);
}

const cli = require.resolve('puppeteer/lib/cjs/puppeteer/node/cli.js');
const args = ['browsers', 'install', 'chrome'];

const result = spawnSync(process.execPath, [cli, ...args], {
  cwd: path.join(__dirname, '..'),
  env,
  stdio: 'inherit',
});

if (result.status !== 0) {
  console.error(
    '[bca_scrapper] Gagal install Chrome. Coba manual:\n'
      + '  cd node/bca_scrapper && npx puppeteer browsers install chrome'
  );
  process.exit(result.status || 1);
}

console.log('[bca_scrapper] Chrome Puppeteer siap.');
