/**
 * Launch Puppeteer — satu tempat untuk opsi Linux VPS + Chrome path.
 */

const puppeteer = require('puppeteer');

const DEFAULT_ARGS = [
  '--no-sandbox',
  '--disable-setuid-sandbox',
  '--disable-dev-shm-usage',
  '--disable-gpu',
];

/**
 * @param {boolean} [headless]
 * @returns {import('puppeteer').LaunchOptions}
 */
function buildLaunchOptions(headless = true) {
  /** @type {import('puppeteer').LaunchOptions} */
  const options = {
    headless,
    args: [...DEFAULT_ARGS],
  };

  const executablePath = String(process.env.PUPPETEER_EXECUTABLE_PATH || '').trim();
  if (executablePath) {
    options.executablePath = executablePath;
  }

  return options;
}

/**
 * @param {boolean} [headless]
 * @returns {Promise<import('puppeteer').Browser>}
 */
async function launchBrowser(headless = true) {
  try {
    return await puppeteer.launch(buildLaunchOptions(headless));
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    if (/could not find chrome/i.test(msg)) {
      const installHint =
        'Chrome Puppeteer belum terinstall. Di VPS jalankan:\n'
        + '  cd node/bca_scrapper && npm run install:chrome\n'
        + 'Atau set PUPPETEER_EXECUTABLE_PATH ke chromium system (/usr/bin/chromium-browser).';
      const wrapped = new Error(`${msg}\n\n${installHint}`);
      wrapped.code = 'chrome_not_found';
      wrapped.cause = err;
      throw wrapped;
    }
    throw err;
  }
}

/**
 * @returns {Promise<{ok:boolean,path?:string,error?:string}>}
 */
async function chromeStatus() {
  try {
    const path = await puppeteer.executablePath();
    return { ok: Boolean(path), path: path || undefined };
  } catch (err) {
    return {
      ok: false,
      error: err instanceof Error ? err.message : String(err),
    };
  }
}

module.exports = {
  launchBrowser,
  buildLaunchOptions,
  chromeStatus,
  DEFAULT_ARGS,
};
