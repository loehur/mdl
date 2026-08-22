const { URLS } = require('./qrms-config');
const { launchBrowser } = require('./puppeteer-launch');

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

/**
 * @param {import('puppeteer').Page} page
 */
async function captureBrowserState(page) {
  const cookies = await page.cookies();
  const storage = await page.evaluate(() => {
    /** @type {Record<string, string>} */
    const out = {};
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key) out[key] = localStorage.getItem(key) || '';
    }
    return out;
  });
  return { cookies, storage };
}

/**
 * Paksa SPA QRMS refresh token — ambil crypto + token baru.
 * @param {import('puppeteer').Page} page
 * @param {number} timeoutMs
 */
async function warmRefreshCryptoFromPage(page, timeoutMs) {
  /** @type {Record<string, string>|null} */
  let refreshForm = null;
  let appVersion = null;

  const waitRefresh = new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('warm_refresh_timeout')), timeoutMs);

    const onRequest = (req) => {
      if (req.method() !== 'POST') return;
      if (!req.url().includes('openid-connect/token')) return;
      const params = Object.fromEntries(new URLSearchParams(req.postData() || ''));
      if (params.grant_type !== 'refresh_token') return;
      refreshForm = params;
      appVersion = req.headers()['x-app-version'] || null;
    };

    const onResponse = async (res) => {
      if (!res.url().includes('openid-connect/token')) return;
      try {
        const body = await res.json();
        if (!body.access_token) return;
        const req = res.request();
        const params = Object.fromEntries(new URLSearchParams(req.postData() || ''));
        if (params.grant_type !== 'refresh_token') return;
        clearTimeout(timer);
        page.off('request', onRequest);
        page.off('response', onResponse);
        resolve({
          tokenJson: body,
          hashKey: params.hash_key,
          xoid: params.xoid,
          appVersion: req.headers()['x-app-version'] || appVersion,
        });
      } catch (_) {
        /* ignore */
      }
    };

    page.on('request', onRequest);
    page.on('response', onResponse);
  });

  await page.evaluate(() => {
    localStorage.setItem('access_token', 'expired');
  });
  await page.reload({ waitUntil: 'networkidle2', timeout: timeoutMs }).catch(() => {});
  await sleep(1500);

  return waitRefresh;
}

/**
 * @param {{
 *   browserCookies?: Array<Record<string, unknown>>,
 *   browserStorage?: Record<string, string>,
 *   refreshToken?: string,
 * }} entry
 * @param {{ headless?: boolean, timeoutMs?: number }} opts
 */
async function refreshViaBrowserState(entry, opts = {}) {
  if (!entry.browserCookies?.length || !entry.browserStorage) {
    throw new Error('browser_state_missing');
  }

  const headless = opts.headless !== false;
  const timeoutMs = Number(opts.timeoutMs || 45000);
  const browser = await launchBrowser(headless);
  const page = await browser.newPage();

  try {
    await page.setCookie(...entry.browserCookies);
    await page.goto(URLS.login, { waitUntil: 'domcontentloaded', timeout: timeoutMs });
    await page.evaluate((storage) => {
      for (const [key, value] of Object.entries(storage)) {
        localStorage.setItem(key, value);
      }
    }, entry.browserStorage);

    const warmed = await warmRefreshCryptoFromPage(page, timeoutMs);
    const browserState = await captureBrowserState(page);

    return {
      ...warmed,
      browserState,
    };
  } finally {
    await browser.close();
  }
}

module.exports = {
  captureBrowserState,
  warmRefreshCryptoFromPage,
  refreshViaBrowserState,
};
