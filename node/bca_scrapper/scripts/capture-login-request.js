require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const { loginQrms } = require('../lib/qrms-puppeteer');

(async () => {
  const email = process.env.BCA_QRMS_EMAIL || '';
  const password = process.env.BCA_QRMS_PASSWORD || '';
  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  const captured = [];

  page.on('request', (req) => {
    if (req.method() !== 'POST') return;
    const url = req.url();
    if (!url.includes('ebanksvc.bca.co.id')) return;
    const post = req.postData() || '';
    const params = Object.fromEntries(new URLSearchParams(post));
    if (params.password) params.password = `[len=${String(params.password).length}]`;
    if (params.client_secret) params.client_secret = `[len=${String(params.client_secret).length}]`;
    captured.push({ url, params, keys: Object.keys(params) });
  });

  try {
    await loginQrms(page, email, password, 60000);
  } catch (err) {
    captured.push({ error: err.message, url: page.url() });
  }

  const out = path.join(__dirname, '..', 'debug', 'login-capture.json');
  fs.writeFileSync(out, JSON.stringify(captured, null, 2));
  console.log(JSON.stringify(captured, null, 2));
  await browser.close();
})();
