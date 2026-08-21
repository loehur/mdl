/**
 * Launch Puppeteer — satu tempat untuk opsi Linux VPS + Chrome path.
 */

const { execFile } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { promisify } = require('util');
const puppeteer = require('puppeteer');

const execFileAsync = promisify(execFile);

const DEFAULT_ARGS = [
  '--no-sandbox',
  '--disable-setuid-sandbox',
  '--disable-dev-shm-usage',
  '--disable-gpu',
  '--disable-crash-reporter',
  '--disable-breakpad',
  '--disable-features=Crashpad',
];

/**
 * @returns {string} base runtime dir
 */
function chromeRuntimeBase() {
  const configured = String(process.env.PUPPETEER_RUNTIME_DIR || '').trim()
    || path.join(os.tmpdir(), 'bca-scrapper-chrome');
  const uid = typeof process.getuid === 'function' ? process.getuid() : 0;
  const user = String(process.env.USER || process.env.LOGNAME || `uid${uid}`).trim() || `uid${uid}`;
  // Pisah per user OS — hindari root test merusak folder service www (crashpad/XDG).
  return path.join(configured, user);
}

/**
 * Set env + folder writable sebelum Chrome spawn (wajib di startup server).
 * @returns {string} base runtime dir
 */
function initChromeRuntime() {
  const base = chromeRuntimeBase();
  const configHome = path.join(base, 'xdg-config');
  const cacheHome = path.join(base, 'xdg-cache');
  const crashDir = path.join(base, 'crash');
  for (const dir of [base, configHome, cacheHome, crashDir]) {
    fs.mkdirSync(dir, { recursive: true, mode: 0o777 });
    try {
      fs.chmodSync(dir, 0o777);
    } catch {
      /* ignore jika bukan owner */
    }
  }
  process.env.XDG_CONFIG_HOME = configHome;
  process.env.XDG_CACHE_HOME = cacheHome;
  process.env.CHROME_CRASHPAD_HANDLER_DATABASE = path.join(crashDir, 'crashpad.db');
  return base;
}

/**
 * @returns {string} userDataDir (unik per launch)
 */
function createChromeProfileDir() {
  const base = initChromeRuntime();
  return fs.mkdtempSync(path.join(base, 'profile-'));
}

/**
 * @param {boolean} [headless]
 * @returns {import('puppeteer').LaunchOptions}
 */
function buildLaunchOptions(headless = true) {
  const base = initChromeRuntime();
  const crashDir = path.join(base, 'crash');
  /** @type {import('puppeteer').LaunchOptions} */
  const options = {
    headless,
    args: [...DEFAULT_ARGS, `--crash-dumps-dir=${crashDir}`],
    userDataDir: createChromeProfileDir(),
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
    if (/could not find chrome|browser was not found/i.test(msg)) {
      const user = process.env.USER || process.env.LOGNAME || 'service-user';
      const installHint =
        'Chrome Puppeteer belum terinstall atau tidak bisa diakses user proses ini.\n'
        + `Proses saat ini: ${user}\n`
        + 'Jangan arahkan cache ke /root/.cache jika service jalan sebagai www.\n'
        + 'Perbaikan (VPS, sebagai root):\n'
        + '  su -s /bin/bash www -c \'cd /path/to/node/bca_scrapper && '
        + 'PUPPETEER_CACHE_DIR=$HOME/.cache/puppeteer npm run install:chrome\'\n'
        + 'Lalu .env: PUPPETEER_CACHE_DIR=/home/www/.cache/puppeteer '
        + '(hapus PUPPETEER_EXECUTABLE_PATH agar auto-detect).';
      const wrapped = new Error(`${msg}\n\n${installHint}`);
      wrapped.code = 'chrome_not_found';
      wrapped.cause = err;
      throw wrapped;
    }
    throw err;
  }
}

/**
 * @param {string} chromePath
 * @returns {Promise<string[]>}
 */
async function missingSharedLibs(chromePath) {
  if (process.platform !== 'linux') {
    return [];
  }
  try {
    const { stdout } = await execFileAsync('ldd', [chromePath]);
    return stdout
      .split('\n')
      .filter((line) => /not found/i.test(line))
      .map((line) => line.trim());
  } catch {
    return [];
  }
}

/**
 * @returns {Promise<{ok:boolean,path?:string,error?:string,user?:string,missing_libs?:string[]}>}
 */
async function chromeStatus() {
  const user = process.env.USER || process.env.LOGNAME || '';
  initChromeRuntime();
  try {
    const chromePath = await puppeteer.executablePath();
    if (!chromePath) {
      return { ok: false, user, error: 'executablePath kosong' };
    }
    await fs.promises.access(chromePath, fs.constants.X_OK);
    const missing = await missingSharedLibs(chromePath);
    if (missing.length > 0) {
      return {
        ok: false,
        path: chromePath,
        user: user || undefined,
        missing_libs: missing,
        error:
          'Library sistem Chrome belum terinstall (ldd). '
          + 'Jalankan di VPS sebagai root: bash scripts/install-chrome-deps.sh',
      };
    }
    return { ok: true, path: chromePath, user: user || undefined };
  } catch (err) {
    let chromePath = '';
    try {
      chromePath = await puppeteer.executablePath();
    } catch (_) {
      /* ignore */
    }
    const base = err instanceof Error ? err.message : String(err);
    const hint =
      chromePath && String(process.env.PUPPETEER_EXECUTABLE_PATH || '').includes('/root/')
        ? ' Path di /root/ — service www tidak bisa baca. Install Chrome sebagai user www.'
        : '';
    return {
      ok: false,
      path: chromePath || undefined,
      user: user || undefined,
      error: base + hint,
    };
  }
}

module.exports = {
  launchBrowser,
  buildLaunchOptions,
  chromeStatus,
  initChromeRuntime,
  DEFAULT_ARGS,
};
