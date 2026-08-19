const {
  parseDateParts,
  parseMutasiHtml,
  parseBalanceHtml,
  isValidBalance,
} = require('./helpers');
const { USER_AGENT, URLS } = require('./config');
const {
  buildLoginPayload,
  isLoginSuccess,
  parseLoginError,
} = require('./ibank-auth');
const debug = require('./debug');

class CookieJar {
  constructor() {
    /** @type {Map<string, string>} */
    this.cookies = new Map();
  }

  /** @param {Response} response */
  storeFromResponse(response) {
    const setCookies =
      typeof response.headers.getSetCookie === 'function'
        ? response.headers.getSetCookie()
        : [];
    for (const raw of setCookies) {
      const [pair] = raw.split(';');
      const eq = pair.indexOf('=');
      if (eq <= 0) continue;
      const name = pair.slice(0, eq).trim();
      const value = pair.slice(eq + 1).trim();
      if (name) this.cookies.set(name, value);
    }
  }

  headerValue() {
    if (this.cookies.size === 0) return '';
    return Array.from(this.cookies.entries())
      .map(([k, v]) => `${k}=${v}`)
      .join('; ');
  }
}

function withTimeout(promise, ms) {
  let timer;
  const timeout = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error('timeout')), ms);
  });
  return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

class BcaHttpClient {
  /**
   * @param {number} timeoutMs
   * @param {ReturnType<typeof debug.beginRun>} debugRun
   */
  constructor(timeoutMs = 30000, debugRun = null) {
    this.timeoutMs = timeoutMs;
    this.jar = new CookieJar();
    this.loggedIn = false;
    this.debugRun = debugRun;
  }

  /** @param {string} uri @param {RequestInit & { form?: Record<string, string|boolean>, debugStep?: string }} options */
  async request(uri, options = {}) {
    const method = options.form ? options.method || 'POST' : options.method || 'GET';
    const stepName = options.debugStep || `${method}_${uri}`;

    debug.step(this.debugRun, `request:${stepName}`, { method, url: uri });

    const headers = {
      'User-Agent': USER_AGENT,
      Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Language': 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
      ...(options.headers || {}),
    };

    const cookie = this.jar.headerValue();
    if (cookie) headers.Cookie = cookie;

    /** @type {RequestInit} */
    const init = {
      method,
      headers,
      redirect: 'follow',
    };

    if (options.form) {
      init.body = new URLSearchParams(
        Object.fromEntries(
          Object.entries(options.form).map(([k, v]) => [k, String(v)])
        )
      );
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      debug.step(this.debugRun, `form:${stepName}`, {
        fields: Object.keys(options.form),
      });
    }

    const res = await withTimeout(fetch(uri, init), this.timeoutMs);
    this.jar.storeFromResponse(res);
    const text = await res.text();

    debug.step(this.debugRun, `response:${stepName}`, {
      status: res.status,
      url: res.url || uri,
      bytes: text.length,
      cookies: this.jar.headerValue() ? 'present' : 'empty',
    });
    debug.saveHtml(this.debugRun, stepName, text, {
      status: res.status,
      url: res.url || uri,
    });

    return { status: res.status, text, url: res.url || uri };
  }

  /** @param {string} username @param {string} password */
  async login(username, password) {
    debug.step(this.debugRun, 'login_start', { username_len: username.length });

    const loadedAt = Date.now();
    const home = await this.request(URLS.home, {
      method: 'GET',
      headers: { Referer: URLS.home },
      debugStep: '01_home',
    });

    const form = buildLoginPayload(home.text, username, password, loadedAt);
    debug.step(this.debugRun, 'login_payload_ready', {
      has_encrypted_password: Boolean(form['value(pswd)']),
      password_field_len: String(form['value(pswd)'] || '').length,
    });

    const result = await this.request(URLS.auth, {
      method: 'POST',
      form,
      headers: { Referer: URLS.home },
      debugStep: '02_login_post',
    });

    const loginOk = isLoginSuccess(result.text);
    debug.step(this.debugRun, 'login_result', {
      success: loginOk,
      error: loginOk ? null : parseLoginError(result.text) || 'login_failed',
    });

    if (!loginOk) {
      throw new Error(parseLoginError(result.text) || 'login_failed');
    }

    this.loggedIn = true;
    return true;
  }

  async openTransactionMenu() {
    await this.request(URLS.auth, {
      method: 'POST',
      form: { 'value(actions)': 'selecttransaction' },
      headers: { Referer: URLS.authWelcome },
      debugStep: '03_selecttransaction',
    });
  }

  async getBalance() {
    await this.request(URLS.authWelcome, {
      method: 'GET',
      headers: { Referer: URLS.auth },
      debugStep: '04_welcome',
    });

    const result = await this.request(URLS.balance, {
      method: 'POST',
      headers: { Referer: URLS.authWelcome },
      debugStep: '05_balance',
    });

    const balance = parseBalanceHtml(result.text);
    debug.saveJson(this.debugRun, 'balance_parsed', balance);
    debug.step(this.debugRun, 'balance_parse', {
      rekening: balance.rekening || '(empty)',
      saldo: balance.saldo,
      accounts: Array.isArray(balance.accounts) ? balance.accounts.length : 0,
    });

    if (!isValidBalance(balance)) {
      throw new Error('balance_parse_failed');
    }
    return balance;
  }

  /**
   * @param {string|Date} [startDate]
   * @param {string|Date} [endDate]
   */
  async getMutasi(startDate, endDate) {
    const start = parseDateParts(startDate || new Date());
    const end = parseDateParts(endDate || startDate || new Date());

    debug.step(this.debugRun, 'mutasi_range', { start: start.iso, end: end.iso });

    await this.request(URLS.accountStmt, {
      method: 'POST',
      form: { 'value(actions)': 'acct_stmt' },
      headers: { Referer: URLS.accountMenu },
      debugStep: '04_acct_stmt',
    });

    const result = await this.request(URLS.accountStmtView, {
      method: 'POST',
      form: {
        'value(D1)': '0',
        'value(r1)': '1',
        'value(startDt)': start.day,
        'value(startMt)': start.month,
        'value(startYr)': start.year,
        'value(endDt)': end.day,
        'value(endMt)': end.month,
        'value(endYr)': end.year,
        'value(submit1)': 'Lihat Mutasi Rekening',
        'value(fDt)': '',
        'value(tDt)': '',
      },
      headers: { Referer: `${URLS.accountStmt}?value(actions)=acct_stmt` },
      debugStep: '05_mutasi_view',
    });

    const mutasi = parseMutasiHtml(result.text);
    debug.saveJson(this.debugRun, 'mutasi_parsed', { count: mutasi.length, mutasi });
    debug.step(this.debugRun, 'mutasi_parse', { count: mutasi.length });

    return {
      start: start.iso,
      end: end.iso,
      mutasi,
    };
  }

  async logout() {
    try {
      await this.request(URLS.auth, {
        method: 'POST',
        form: { 'value(actions)': 'logout' },
        headers: { Referer: URLS.top },
        debugStep: '99_logout',
      });
    } catch (err) {
      debug.step(this.debugRun, 'logout_error', {
        error: err instanceof Error ? err.message : String(err),
      });
    } finally {
      this.loggedIn = false;
    }
  }
}

/**
 * @param {{ username: string, password: string, startDate?: string|Date, endDate?: string|Date, timeoutMs?: number }} opts
 */
async function fetchBalanceHttp(opts) {
  const run = debug.beginRun('http-balance');
  const client = new BcaHttpClient(opts.timeoutMs, run);
  try {
    await client.login(opts.username, opts.password);
    const balance = await client.getBalance();
    debug.finishRun(run, { ok: true, balance });
    return balance;
  } catch (err) {
    debug.step(run, 'run_failed', {
      error: err instanceof Error ? err.message : String(err),
    });
    debug.finishRun(run, {
      ok: false,
      error: err instanceof Error ? err.message : String(err),
    });
    throw err;
  } finally {
    await client.logout();
  }
}

/**
 * @param {{ username: string, password: string, startDate?: string|Date, endDate?: string|Date, timeoutMs?: number }} opts
 */
async function fetchMutasiHttp(opts) {
  const run = debug.beginRun('http-mutasi');
  const client = new BcaHttpClient(opts.timeoutMs, run);
  try {
    await client.login(opts.username, opts.password);
    await client.openTransactionMenu();
    const data = await client.getMutasi(opts.startDate, opts.endDate);
    debug.finishRun(run, { ok: true, count: data.mutasi.length });
    return data;
  } catch (err) {
    debug.step(run, 'run_failed', {
      error: err instanceof Error ? err.message : String(err),
    });
    debug.finishRun(run, {
      ok: false,
      error: err instanceof Error ? err.message : String(err),
    });
    throw err;
  } finally {
    await client.logout();
  }
}

module.exports = {
  BcaHttpClient,
  fetchBalanceHttp,
  fetchMutasiHttp,
};
