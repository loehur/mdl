const { USER_AGENT, URLS } = require('./qrms-config');
const {
  parseTransactionsFromApiBodies,
  parseTransactionsFromHtml,
  filterByDateRange,
  ymdToParts,
} = require('./qrms-parser');
const debug = require('./debug');
const { launchBrowser: launchPuppeteerBrowser } = require('./puppeteer-launch');

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

/**
 * @param {{ headless?: boolean, timeoutMs?: number }} options
 */
async function launchBrowser(options = {}) {
  const headless = options.headless !== false;
  const timeoutMs = Number(options.timeoutMs || 60000);
  const browser = await launchPuppeteerBrowser(headless);
  return { browser, timeoutMs };
}

function isApiUrl(url) {
  return /ebanksvc\.bca\.co\.id/i.test(url);
}

function isJsonContentType(ct) {
  return (ct || '').includes('json');
}

/**
 * @param {import('puppeteer').Page} page
 * @param {ReturnType<typeof debug.beginRun>} run
 */
function attachApiCapture(page, run) {
  /** @type {{ url: string, method: string, status: number, body: unknown }[]} */
  const captured = [];

  page.on('response', async (res) => {
    const url = res.url();
    if (!isApiUrl(url)) return;
    const ct = res.headers()['content-type'] || '';
    if (!isJsonContentType(ct)) return;
    try {
      const body = await res.json();
      captured.push({
        url,
        method: res.request().method(),
        status: res.status(),
        body,
      });
      if (run) debug.step(run, 'api_capture', { url, status: res.status() });
    } catch (_) {
      /* ignore */
    }
  });

  return captured;
}

/**
 * @param {import('puppeteer').Page} page
 * @param {string} selector
 * @param {string} value
 */
async function fillInput(page, selector, value) {
  await page.waitForSelector(selector, { visible: true });
  await page.focus(selector);
  await page.click(selector, { clickCount: 3 });
  await page.keyboard.press('Backspace');
  await page.type(selector, value, { delay: 35 });
  await page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    el.dispatchEvent(new Event('blur', { bubbles: true }));
  }, selector);
}

/**
 * Isi form login & klik Masuk (tanpa tunggu navigasi selesai).
 * @param {import('puppeteer').Page} page
 * @param {string} email
 * @param {string} password
 * @param {number} timeoutMs
 */
async function submitLoginForm(page, email, password, timeoutMs) {
  await page.setUserAgent(USER_AGENT);
  await page.goto(URLS.login, { waitUntil: 'domcontentloaded', timeout: timeoutMs });
  await sleep(2000);

  await page.waitForSelector('input[type="email"]', { timeout: timeoutMs });
  await page.waitForSelector('input[type="password"]', { timeout: timeoutMs });

  await fillInput(page, 'input[type="email"]', email);
  await fillInput(page, 'input[type="password"]', password);

  await page
    .waitForFunction(
      () => {
        const btn = document.querySelector('button[type="submit"]');
        return btn && !btn.disabled;
      },
      { timeout: 15000 }
    )
    .catch(() => {});

  await page.evaluate(() => {
    const btn = document.querySelector('button[type="submit"]');
    if (btn && !btn.disabled) btn.click();
  });
}

/**
 * @param {import('puppeteer').Page} page
 * @param {string} email
 * @param {string} password
 * @param {number} timeoutMs
 * @param {ReturnType<typeof debug.beginRun>} [run]
 */
async function loginQrms(page, email, password, timeoutMs, run = null) {
  await submitLoginForm(page, email, password, timeoutMs);

  await Promise.race([
    page.waitForFunction(() => !/\/login/i.test(location.pathname), { timeout: timeoutMs }),
    page.waitForNavigation({ waitUntil: 'networkidle2', timeout: timeoutMs }),
  ]).catch(() => {});

  await sleep(3000);

  const url = page.url();
  if (run) debug.saveHtml(run, 'login_result', await page.content(), { url });

  if (/\/login/i.test(url)) {
    const errText = await page.evaluate(() => document.body?.innerText?.slice(0, 1200) || '');
    if (/salah|invalid|gagal|failed|incorrect|tidak valid|wrong/i.test(errText)) {
      throw new Error('login_failed');
    }
    throw new Error('login_failed_still_on_login_page');
  }
}

/**
 * Coba set filter tanggal di UI Angular (bs-datepicker / input text).
 * @param {import('puppeteer').Page} page
 * @param {string} startYmd
 * @param {string} endYmd
 */
async function applyDateFilter(page, startYmd, endYmd) {
  const start = ymdToParts(startYmd);
  const end = ymdToParts(endYmd);
  const fmt = (p) => `${String(p.d).padStart(2, '0')}/${String(p.m).padStart(2, '0')}/${p.y}`;
  const startStr = fmt(start);
  const endStr = fmt(end);

  const applied = await page.evaluate(
    (fromStr, toStr) => {
      const inputs = [...document.querySelectorAll('input')].filter(
        (el) => !['email', 'password', 'hidden', 'checkbox'].includes(el.type)
      );
      if (inputs.length === 0) return false;

      const dateLike = inputs.filter(
        (el) =>
          /date|tanggal|periode|period|dari|sampai|from|to/i.test(
            `${el.placeholder || ''} ${el.name || ''} ${el.id || ''} ${el.className || ''}`
          ) || el.type === 'text'
      );

      const targets = dateLike.length >= 1 ? dateLike : inputs;
      const setVal = (el, val) => {
        el.focus();
        el.value = val;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.dispatchEvent(new Event('blur', { bubbles: true }));
      };

      if (targets.length >= 2) {
        setVal(targets[0], fromStr);
        setVal(targets[1], toStr);
      } else if (targets.length === 1) {
        setVal(targets[0], fromStr);
      } else {
        return false;
      }

      const btn = [...document.querySelectorAll('button')].find((b) =>
        /cari|filter|lihat|search|tampilkan|submit/i.test(b.textContent || '')
      );
      if (btn) btn.click();
      return true;
    },
    startStr,
    endStr
  );

  if (applied) await sleep(4000);
  return applied;
}

/**
 * @param {{
 *   email: string,
 *   password: string,
 *   startDate: string,
 *   endDate: string,
 *   headless?: boolean,
 *   timeoutMs?: number,
 * }} opts
 */
async function fetchQrisTransactionsPuppeteer(opts) {
  const run = debug.beginRun('puppeteer-qris-transactions');
  const { browser, timeoutMs } = await launchBrowser(opts);
  const page = await browser.newPage();
  const captured = attachApiCapture(page, run);

  try {
    debug.step(run, 'qrms_login_start');
    await loginQrms(page, opts.email, opts.password, timeoutMs, run);
    debug.saveHtml(run, 'after_login', await page.content(), { url: page.url() });

    debug.step(run, 'qrms_apply_date_filter', { start: opts.startDate, end: opts.endDate });
    await applyDateFilter(page, opts.startDate, opts.endDate);
    await sleep(2000);

    debug.saveHtml(run, 'transactions_page', await page.content(), { url: page.url() });

    let transactions = parseTransactionsFromApiBodies(captured.map((c) => c.body));
    if (transactions.length === 0) {
      transactions = parseTransactionsFromHtml(await page.content());
    }
    transactions = filterByDateRange(transactions, opts.startDate, opts.endDate);

    debug.saveJson(run, 'api_captured', captured);
    debug.saveJson(run, 'transactions_parsed', { count: transactions.length, transactions });

    if (transactions.length === 0 && captured.length === 0) {
      const html = await page.content();
      if (/login|masukkan.*email/i.test(html) && /password/i.test(html)) {
        throw new Error('login_failed');
      }
    }

    debug.finishRun(run, {
      ok: true,
      count: transactions.length,
      api_calls: captured.length,
    });
    return {
      start: opts.startDate,
      end: opts.endDate,
      transactions,
      api_calls: captured.length,
    };
  } catch (err) {
    debug.step(run, 'run_failed', { error: err instanceof Error ? err.message : String(err) });
    debug.saveJson(run, 'api_captured', captured);
    debug.finishRun(run, { ok: false, error: err instanceof Error ? err.message : String(err) });
    throw err;
  } finally {
    await browser.close();
  }
}

module.exports = {
  fetchQrisTransactionsPuppeteer,
  loginQrms,
  submitLoginForm,
};
