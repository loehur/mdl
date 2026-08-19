const { fetchBalanceHttp, fetchMutasiHttp } = require('./http');
const { fetchBalancePuppeteer, fetchMutasiPuppeteer } = require('./puppeteer');

const debug = require('./debug');

/**
 * @param {() => Promise<any>} httpFn
 * @param {() => Promise<any>} puppeteerFn
 * @param {string} label
 */
async function withHttpFallback(httpFn, puppeteerFn, label) {
  let httpError = null;

  try {
    const data = await httpFn();
    return { ok: true, method: 'http', data };
  } catch (err) {
    httpError = err instanceof Error ? err.message : String(err);
    console.warn(`[bca_scrapper] HTTP ${label} failed:`, httpError);
    if (debug.isEnabled()) {
      console.warn(
        `[bca_scrapper] Lihat folder debug/ untuk HTML step HTTP (${label}) sebelum fallback Puppeteer`
      );
    }
  }

  try {
    const data = await puppeteerFn();
    return { ok: true, method: 'puppeteer', data, http_error: httpError };
  } catch (err) {
    const puppeteerError = err instanceof Error ? err.message : String(err);
    console.error(`[bca_scrapper] Puppeteer ${label} failed:`, puppeteerError);
    const error = new Error(
      httpError
        ? `HTTP gagal (${httpError}); Puppeteer gagal (${puppeteerError})`
        : `Puppeteer gagal (${puppeteerError})`
    );
    error.code = 'scrape_failed';
    error.http_error = httpError;
    error.puppeteer_error = puppeteerError;
    throw error;
  }
}

/**
 * @param {{
 *   username: string,
 *   password: string,
 *   startDate?: string|Date,
 *   endDate?: string|Date,
 *   httpTimeoutMs?: number,
 *   puppeteerHeadless?: boolean,
 *   puppeteerTimeoutMs?: number,
 * }} opts
 */
async function getBalance(opts) {
  return withHttpFallback(
    () =>
      fetchBalanceHttp({
        username: opts.username,
        password: opts.password,
        timeoutMs: opts.httpTimeoutMs,
      }),
    () =>
      fetchBalancePuppeteer({
        username: opts.username,
        password: opts.password,
        headless: opts.puppeteerHeadless,
        timeoutMs: opts.puppeteerTimeoutMs,
      }),
    'balance'
  );
}

/**
 * @param {{
 *   username: string,
 *   password: string,
 *   startDate?: string|Date,
 *   endDate?: string|Date,
 *   httpTimeoutMs?: number,
 *   puppeteerHeadless?: boolean,
 *   puppeteerTimeoutMs?: number,
 * }} opts
 */
async function getMutasi(opts) {
  return withHttpFallback(
    () =>
      fetchMutasiHttp({
        username: opts.username,
        password: opts.password,
        startDate: opts.startDate,
        endDate: opts.endDate,
        timeoutMs: opts.httpTimeoutMs,
      }),
    () =>
      fetchMutasiPuppeteer({
        username: opts.username,
        password: opts.password,
        startDate: opts.startDate,
        endDate: opts.endDate,
        headless: opts.puppeteerHeadless,
        timeoutMs: opts.puppeteerTimeoutMs,
      }),
    'mutasi'
  );
}

module.exports = {
  getBalance,
  getMutasi,
};
