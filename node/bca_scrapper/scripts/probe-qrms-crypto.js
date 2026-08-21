/**
 * Extract encrypted login payload using browser crypto (fast, no full scrape).
 */
require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });

const puppeteer = require('puppeteer');

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

(async () => {
  const email = process.env.BCA_QRMS_EMAIL;
  const password = process.env.BCA_QRMS_PASSWORD;
  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.goto('https://qr.klikbca.com/login', { waitUntil: 'networkidle2' });
  await sleep(3000);

  const result = await page.evaluate(async (em, pw) => {
    const ng = window.ng;
    const root = document.querySelector('app-root');
    if (!ng || !root) return { error: 'no_angular' };

    let svc = null;
    try {
      const cmp = ng.getComponent(root);
      const inj = cmp?.injector || ng.getInjector(root);
      // walk providers — brute search login/token service
      const tokens = inj?.records ? [...inj.records.keys()] : [];
      return { tokens: tokens.slice(0, 30).map(String), cmp: !!cmp };
    } catch (e) {
      return { error: e.message };
    }
  }, email, password);

  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})();
