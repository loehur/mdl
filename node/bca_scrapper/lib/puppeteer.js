const { parseDateParts, parseMutasiHtml, parseBalanceHtml, isValidBalance } = require('./helpers');
const { USER_AGENT, URLS } = require('./config');
const { isLoginSuccess } = require('./ibank-auth');
const debug = require('./debug');
const { launchBrowser: launchPuppeteerBrowser } = require('./puppeteer-launch');

/**
 * @param {{ headless?: boolean, timeoutMs?: number }} options
 */
async function launchBrowser(options = {}) {
  const headless = options.headless !== false;
  const timeoutMs = Number(options.timeoutMs || 60000);
  const browser = await launchPuppeteerBrowser(headless);
  return { browser, timeoutMs };
}

/**
 * Login via ibank.klikbca.com — biarkan JS halaman yang enkripsi PIN.
 * @param {import('puppeteer').Page} page
 * @param {string} username
 * @param {string} password
 * @param {number} timeoutMs
 */
async function loginPage(page, username, password, timeoutMs) {
  await page.setUserAgent(USER_AGENT);
  await page.goto(URLS.home, { waitUntil: 'networkidle2', timeout: timeoutMs });

  await page.waitForSelector('#txt_user_id', { timeout: timeoutMs });
  await page.click('#txt_user_id', { clickCount: 3 });
  await page.type('#txt_user_id', username, { delay: 25 });
  await page.click('#txt_pswd', { clickCount: 3 });
  await page.type('#txt_pswd', password, { delay: 25 });

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2', timeout: timeoutMs }),
    page.click('#btnSubmit'),
  ]);

  const html = await page.content();
  if (!isLoginSuccess(html)) {
    throw new Error('login_failed');
  }
}

/**
 * @param {import('puppeteer').Page} page
 * @param {number} timeoutMs
 */
async function openTransactionMenu(page, timeoutMs) {
  await page.goto(URLS.authWelcome, { waitUntil: 'networkidle2', timeout: timeoutMs });
  await page.evaluate(async (authUrl) => {
    const body = new URLSearchParams({ 'value(actions)': 'selecttransaction' });
    await fetch(authUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
      credentials: 'include',
    });
  }, URLS.auth);
}

/**
 * @param {import('puppeteer').Page} page
 * @param {number} timeoutMs
 */
async function logoutPage(page, timeoutMs) {
  try {
    await page.evaluate(async (authUrl) => {
      const body = new URLSearchParams({ 'value(actions)': 'logout' });
      await fetch(authUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
        credentials: 'include',
      });
    }, URLS.auth);
    await page.goto(URLS.home, { waitUntil: 'domcontentloaded', timeout: timeoutMs });
  } catch (_) {
    /* ignore */
  }
}

/**
 * @param {{ username: string, password: string, headless?: boolean, timeoutMs?: number }} opts
 */
async function fetchBalancePuppeteer(opts) {
  const run = debug.beginRun('puppeteer-balance');
  const { browser, timeoutMs } = await launchBrowser(opts);
  const page = await browser.newPage();
  try {
    debug.step(run, 'puppeteer_login_start');
    await loginPage(page, opts.username, opts.password, timeoutMs);
    await page.goto(URLS.authWelcome, { waitUntil: 'networkidle2', timeout: timeoutMs });
    debug.saveHtml(run, 'welcome', await page.content());
    await page.goto(URLS.balance, { waitUntil: 'networkidle2', timeout: timeoutMs });
    const html = await page.content();
    debug.saveHtml(run, 'balance', html);
    const balance = parseBalanceHtml(html);
    debug.saveJson(run, 'balance_parsed', balance);
    if (!isValidBalance(balance)) {
      throw new Error('balance_parse_failed');
    }
    debug.finishRun(run, { ok: true, balance });
    return balance;
  } catch (err) {
    debug.step(run, 'run_failed', { error: err instanceof Error ? err.message : String(err) });
    debug.finishRun(run, { ok: false, error: err instanceof Error ? err.message : String(err) });
    throw err;
  } finally {
    await logoutPage(page, timeoutMs);
    await browser.close();
  }
}

/**
 * @param {{ username: string, password: string, startDate?: string|Date, endDate?: string|Date, headless?: boolean, timeoutMs?: number }} opts
 */
async function fetchMutasiPuppeteer(opts) {
  const run = debug.beginRun('puppeteer-mutasi');
  const start = parseDateParts(opts.startDate || new Date());
  const end = parseDateParts(opts.endDate || opts.startDate || new Date());
  const { browser, timeoutMs } = await launchBrowser(opts);
  const page = await browser.newPage();

  try {
    debug.step(run, 'puppeteer_mutasi_start', { start: start.iso, end: end.iso });
    await loginPage(page, opts.username, opts.password, timeoutMs);
    await openTransactionMenu(page, timeoutMs);

    await page.goto(`${URLS.accountStmt}?value(actions)=acct_stmt`, {
      waitUntil: 'networkidle2',
      timeout: timeoutMs,
    });
    debug.saveHtml(run, 'acct_stmt', await page.content());

    await page.evaluate(
      (parts, viewUrl) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = viewUrl;
        const fields = {
          'value(D1)': '0',
          'value(r1)': '1',
          'value(startDt)': parts.startDay,
          'value(startMt)': parts.startMonth,
          'value(startYr)': parts.startYear,
          'value(endDt)': parts.endDay,
          'value(endMt)': parts.endMonth,
          'value(endYr)': parts.endYear,
          'value(submit1)': 'Lihat Mutasi Rekening',
          'value(fDt)': '',
          'value(tDt)': '',
        };
        for (const [name, value] of Object.entries(fields)) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          input.value = value;
          form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
      },
      {
        startDay: start.day,
        startMonth: start.month,
        startYear: start.year,
        endDay: end.day,
        endMonth: end.month,
        endYear: end.year,
      },
      URLS.accountStmtView
    );

    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: timeoutMs });
    const html = await page.content();
    debug.saveHtml(run, 'mutasi_view', html);
    const mutasi = parseMutasiHtml(html);
    debug.saveJson(run, 'mutasi_parsed', { count: mutasi.length, mutasi });
    const data = {
      start: start.iso,
      end: end.iso,
      mutasi,
    };
    debug.finishRun(run, { ok: true, count: mutasi.length });
    return data;
  } catch (err) {
    debug.step(run, 'run_failed', { error: err instanceof Error ? err.message : String(err) });
    debug.finishRun(run, { ok: false, error: err instanceof Error ? err.message : String(err) });
    throw err;
  } finally {
    await logoutPage(page, timeoutMs);
    await browser.close();
  }
}

module.exports = {
  fetchBalancePuppeteer,
  fetchMutasiPuppeteer,
};
