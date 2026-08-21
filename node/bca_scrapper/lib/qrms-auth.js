const { submitLoginForm } = require('./qrms-puppeteer');
const { launchBrowser: launchPuppeteerBrowser } = require('./puppeteer-launch');

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

/**
 * Ambil payload login terenkripsi + x-app-version dari request token.
 * @param {string} email
 * @param {string} password
 * @param {{ headless?: boolean, timeoutMs?: number }} [opts]
 * @returns {Promise<{ loginForm: Record<string, string>, appVersion: string|null }>}
 */
async function buildEncryptedLoginPayload(email, password, opts = {}) {
  const headless = opts.headless !== false;
  const timeoutMs = Number(opts.timeoutMs || 45000);
  const keepBrowser = opts.keepBrowser === true;
  let appVersion = null;

  const browser = await launchPuppeteerBrowser(headless);

  try {
    const page = await browser.newPage();
    /** @type {Promise<string|null>} */
    let resolveCapture;
    const capturedPromise = new Promise((resolve) => {
      resolveCapture = resolve;
    });

    page.on('request', (req) => {
      if (req.method() !== 'POST') return;
      if (!req.url().includes('openid-connect/token')) return;
      const h = req.headers();
      if (h['x-app-version']) appVersion = h['x-app-version'];
      resolveCapture(req.postData() || '');
    });

    await submitLoginForm(page, email, password, timeoutMs);

    const captured = await Promise.race([
      capturedPromise,
      sleep(12000).then(() => null),
    ]);

    if (!captured) {
      throw new Error('login_payload_capture_failed');
    }

    if (keepBrowser) {
      await Promise.race([
        page.waitForFunction(() => !/\/login/i.test(location.pathname), { timeout: timeoutMs }),
        sleep(8000),
      ]).catch(() => {});
      const { waitForDashboardTransactions } = require('./qrms-dom');
      await waitForDashboardTransactions(page, 12000);
    }

    const result = {
      loginForm: Object.fromEntries(new URLSearchParams(captured)),
      appVersion,
    };

    if (keepBrowser) {
      result.browser = browser;
      result.page = page;
      result.release = async () => {
        await browser.close();
      };
      return result;
    }

    await browser.close();
    return result;
  } catch (err) {
    await browser.close();
    throw err;
  }
}

module.exports = {
  buildEncryptedLoginPayload,
};
