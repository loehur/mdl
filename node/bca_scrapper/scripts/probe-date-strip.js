require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });
const puppeteer = require('puppeteer');
const { submitLoginForm } = require('../lib/qrms-puppeteer');
const { waitForDashboardTransactions, selectDashboardDate } = require('../lib/qrms-dom');

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

(async () => {
  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await submitLoginForm(page, process.env.BCA_QRMS_EMAIL, process.env.BCA_QRMS_PASSWORD, 45000);
  await waitForDashboardTransactions(page, 15000);
  await sleep(2000);

  const before = await page.evaluate(() => {
    const items = [];
    for (const el of document.querySelectorAll('*')) {
      const t = (el.innerText || '').replace(/\s+/g, ' ').trim();
      if (!t || t.length > 30) continue;
      if (/\b(Agu|20|21)\b/.test(t)) {
        const r = el.getBoundingClientRect();
        items.push({ tag: el.tagName, class: String(el.className).slice(0, 60), text: t, w: r.width, h: r.height });
      }
    }
    return items.slice(0, 80);
  });
  console.log('BEFORE select 20:', JSON.stringify(before, null, 2));

  const ok = await selectDashboardDate(page, '2026-08-20');
  console.log('selectDashboardDate ok:', ok);
  await sleep(2000);

  const afterText = await page.evaluate(() => document.body.innerText.slice(0, 2500));
  console.log('BODY AFTER:\n', afterText);

  await browser.close();
})();
