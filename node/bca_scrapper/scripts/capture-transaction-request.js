/**
 * Login sekali, tangkap request transaksi asli dari browser.
 * node scripts/capture-transaction-request.js
 */
require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const { submitLoginForm } = require('../lib/qrms-puppeteer');

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

(async () => {
  const email = process.env.BCA_QRMS_EMAIL;
  const password = process.env.BCA_QRMS_PASSWORD;
  const out = path.join(__dirname, '..', 'debug', 'transaction-capture.json');
  const captured = [];

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();

  page.on('request', (req) => {
    const url = req.url();
    if (!url.includes('ebanksvc.bca.co.id')) return;
    if (req.method() !== 'POST' && req.method() !== 'GET') return;
    captured.push({
      url,
      method: req.method(),
      headers: req.headers(),
      postData: req.postData()?.slice(0, 2000) || null,
    });
  });

  page.on('response', async (res) => {
    const url = res.url();
    if (!url.includes('ebanksvc.bca.co.id')) return;
    const ct = res.headers()['content-type'] || '';
    if (!ct.includes('json')) return;
    try {
      const body = await res.json();
      captured.push({
        url,
        status: res.status(),
        response: body,
      });
    } catch (_) {}
  });

  console.log('Login...');
  await submitLoginForm(page, email, password, 45000);
  await sleep(8000);

  console.log('URL after login:', page.url());
  await page.screenshot({ path: path.join(__dirname, '..', 'debug', 'dashboard.png'), fullPage: true });

  // Tunggu API transaksi auto-load
  await sleep(10000);

  fs.writeFileSync(out, JSON.stringify({ final_url: page.url(), captured }, null, 2));
  console.log('Saved', out, 'events', captured.length);

  const trx = captured.filter((c) => /transaction|outlet|member/i.test(c.url || ''));
  console.log('transaction-related:', trx.length);
  for (const t of trx.slice(0, 10)) {
    console.log(JSON.stringify(t, null, 2).slice(0, 800));
    console.log('---');
  }

  await browser.close();
})();
