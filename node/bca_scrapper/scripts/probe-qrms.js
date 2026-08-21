/**
 * Probe qr.klikbca.com — discover login selectors & API endpoints.
 * Usage: node scripts/probe-qrms.js
 * Optional: QRMS_EMAIL=... QRMS_PASSWORD=... node scripts/probe-qrms.js
 */
require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const OUT = path.join(__dirname, '..', 'debug', 'qrms-probe');

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function main() {
  fs.mkdirSync(OUT, { recursive: true });

  const email = process.env.QRMS_EMAIL || process.env.BCA_QRMS_EMAIL || '';
  const password = process.env.QRMS_PASSWORD || process.env.BCA_QRMS_PASSWORD || '';

  const browser = await puppeteer.launch({
    headless: String(process.env.PUPPETEER_HEADLESS || 'false').toLowerCase() !== 'false',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const page = await browser.newPage();
  const apiCalls = [];

  page.on('response', async (res) => {
    const url = res.url();
    if (!url.includes('klikbca.com')) return;
    const ct = res.headers()['content-type'] || '';
    let body = null;
    try {
      if (ct.includes('json')) body = await res.json();
      else if (ct.includes('text') || ct.includes('javascript')) {
        const t = await res.text();
        body = t.length > 500 ? t.slice(0, 500) + '...' : t;
      }
    } catch (_) {}
    apiCalls.push({ url, status: res.status(), method: res.request().method(), body });
  });

  await page.goto('https://qr.klikbca.com/login', { waitUntil: 'networkidle2', timeout: 60000 });
  await sleep(3000);

  const loginDom = await page.evaluate(() => {
    const inputs = [...document.querySelectorAll('input')].map((el) => ({
      type: el.type,
      name: el.name,
      id: el.id,
      placeholder: el.placeholder,
      formControlName: el.getAttribute('formcontrolname'),
      className: el.className,
    }));
    const buttons = [...document.querySelectorAll('button')].map((el) => ({
      type: el.type,
      text: el.textContent?.trim().slice(0, 80),
      className: el.className,
    }));
    return { title: document.title, url: location.href, inputs, buttons };
  });

  fs.writeFileSync(path.join(OUT, 'login-dom.json'), JSON.stringify(loginDom, null, 2));
  await page.screenshot({ path: path.join(OUT, 'login.png'), fullPage: true });

  if (email && password) {
    console.log('Attempting login...');
    await page.evaluate(
      (em, pw) => {
        const inputs = [...document.querySelectorAll('input')];
        const emailInput = inputs.find(
          (i) =>
            i.type === 'email' ||
            /email/i.test(i.placeholder || '') ||
            /email/i.test(i.getAttribute('formcontrolname') || '')
        );
        const passInput = inputs.find((i) => i.type === 'password');
        if (emailInput) {
          emailInput.value = em;
          emailInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (passInput) {
          passInput.value = pw;
          passInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const btn = [...document.querySelectorAll('button')].find((b) =>
          /masuk|login|sign/i.test(b.textContent || '')
        );
        if (btn) btn.click();
      },
      email,
      password
    );

    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {});
    await sleep(5000);

    fs.writeFileSync(path.join(OUT, 'after-login-url.txt'), page.url());
    await page.screenshot({ path: path.join(OUT, 'after-login.png'), fullPage: true });

    const postLoginDom = await page.evaluate(() => ({
      url: location.href,
      links: [...document.querySelectorAll('a')].slice(0, 30).map((a) => ({
        href: a.getAttribute('href'),
        text: a.textContent?.trim().slice(0, 60),
      })),
      navText: document.body?.innerText?.slice(0, 2000),
    }));
    fs.writeFileSync(path.join(OUT, 'after-login-dom.json'), JSON.stringify(postLoginDom, null, 2));
  }

  fs.writeFileSync(path.join(OUT, 'api-calls.json'), JSON.stringify(apiCalls, null, 2));
  console.log('Probe done →', OUT);
  console.log('Login inputs:', JSON.stringify(loginDom.inputs, null, 2));
  console.log('API calls:', apiCalls.length);

  await browser.close();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
