#!/usr/bin/env node
/** Tes launch Chrome di VPS: node scripts/test-chrome-launch.js */
require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });

const { initChromeRuntime, launchBrowser } = require('../lib/puppeteer-launch');

async function main() {
  const base = initChromeRuntime();
  console.log('[test] runtime dir:', base);
  console.log('[test] OS user:', process.env.USER || process.env.LOGNAME || process.getuid?.());
  console.log('[test] XDG_CONFIG_HOME:', process.env.XDG_CONFIG_HOME);
  console.log('[test] XDG_CACHE_HOME:', process.env.XDG_CACHE_HOME);

  const browser = await launchBrowser(true);
  const page = await browser.newPage();
  await page.goto('about:blank', { waitUntil: 'domcontentloaded', timeout: 15000 });
  const title = await page.title();
  await browser.close();
  console.log('[test] OK — Chrome launch berhasil, title:', JSON.stringify(title));
}

main().catch((err) => {
  console.error('[test] GAGAL:', err instanceof Error ? err.message : err);
  process.exit(1);
});
