const { API, URLS, USER_AGENT } = require('./qrms-config');
const { buildEncryptedLoginPayload } = require('./qrms-auth');
const qrmsSession = require('./qrms-session');
const { scrapeTransactionsForDateRange } = require('./qrms-dom');
const {
  parseTransactionsFromJson,
  filterByDateRange,
} = require('./qrms-parser');
const debug = require('./debug');

function withTimeout(promise, ms) {
  let timer;
  const timeout = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error('timeout')), ms);
  });
  return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

function bmsHeaders(token, opts = {}) {
  const headers = {
    Accept: 'application/json, text/plain, */*',
    Authorization: `bearer ${token}`,
    Referer: URLS.referer,
    'Accept-Language': 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
    'User-Agent': USER_AGENT,
  };
  if (opts.appVersion) headers['x-app-version'] = opts.appVersion;
  if (opts.decryptMcb) headers['x-decrypt-mcb'] = 'true';
  if (opts.afterLogin) headers['x-after-login'] = 'true';
  return headers;
}

/**
 * @param {Record<string, string>} loginForm
 * @param {number} timeoutMs
 */
async function fetchToken(loginForm, timeoutMs) {
  const res = await withTimeout(
    fetch(API.token, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Accept: 'application/json',
        'User-Agent': USER_AGENT,
      },
      body: new URLSearchParams(loginForm),
    }),
    timeoutMs
  );

  const text = await res.text();
  let json = null;
  try {
    json = JSON.parse(text);
  } catch (_) {
    json = { raw: text.slice(0, 300) };
  }

  if (!res.ok) {
    const msg =
      (json && (json.error_description || json.error)) ||
      `token_http_${res.status}`;
    const err = new Error(msg);
    const oauthError = json && json.error ? String(json.error) : '';
    err.code =
      oauthError === 'invalid_grant' || oauthError === 'invalid_token'
        ? 'token_failed'
        : res.status === 401
          ? 'token_failed'
          : 'token_failed';
    if (oauthError === 'invalid_grant' && /password|credential/i.test(msg)) {
      err.code = 'login_failed';
    }
    err.response = json;
    throw err;
  }

  if (!json || !json.access_token) {
    throw new Error('token_missing_access_token');
  }

  return json;
}

/**
 * @param {string} token
 * @param {string} url
 * @param {number} timeoutMs
 * @param {{ decryptMcb?: boolean, afterLogin?: boolean }} [headerOpts]
 */
async function getJson(token, url, timeoutMs, headerOpts = {}) {
  const res = await withTimeout(
    fetch(url, {
      method: 'GET',
      headers: bmsHeaders(token, headerOpts),
    }),
    timeoutMs
  );

  const text = await res.text();
  let json = null;
  try {
    json = JSON.parse(text);
  } catch (_) {
    json = { raw: text.slice(0, 500) };
  }

  if (!res.ok) {
    const err = new Error(`api_http_${res.status}`);
    err.code = res.status === 401 ? 'token_failed' : 'api_failed';
    err.response = json;
    err.url = url;
    throw err;
  }

  const code = json?.error_schema?.error_code;
  if (code && code !== 'MSV-200-000') {
    const msg =
      json?.error_schema?.error_message?.indonesian ||
      json?.error_schema?.error_message?.english ||
      code;
    const err = new Error(msg);
    err.code = 'api_failed';
    err.response = json;
    err.url = url;
    throw err;
  }

  return json;
}

function isMcbEncryptedResponse(json) {
  if (!json || typeof json !== 'object') return false;
  if (json.output_schema) return false;
  if (json.error_schema) return false;
  return typeof json.raw === 'string' && json.raw.length > 20;
}

function buildTransactionUrl(mid, startYmd, endYmd, page = 1, size = 200) {
  const endTime = endYmd === todayYmd() ? nowTimeHms(TZ) : '23:59:59';
  const params = new URLSearchParams({
    'start-date': `${startYmd}:00:00:00`,
    'end-date': `${endYmd}:${endTime}`,
    'last-total-transaction': '0',
    page: String(page),
    size: String(size),
    sorter: '',
    filter: '',
    mid,
  });
  return `${API.transactionList}?${params}`;
}

const TZ = process.env.TZ || 'Asia/Jakarta';

function todayYmd() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: TZ }).format(new Date());
}

function nowTimeHms(timeZone) {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).formatToParts(new Date());
  const get = (type) => parts.find((p) => p.type === type)?.value || '00';
  return `${get('hour')}:${get('minute')}:${get('second')}`;
}

function flattenTransactionPayload(json) {
  if (!json || typeof json !== 'object') return [];
  const rows = parseTransactionsFromJson(json);
  if (rows.length) return rows;
  const schema = json.output_schema || json.outputSchema;
  if (schema) return parseTransactionsFromJson(schema);
  return [];
}

/**
 * @param {string} token
 * @param {number} timeoutMs
 * @param {string|null} appVersion
 */
async function fetchOutlets(token, timeoutMs, appVersion) {
  const url = `${API.outletList}?prev=0&next=1&size=50`;
  const json = await getJson(token, url, timeoutMs, { appVersion });
  const list = json?.output_schema?.list_data || json?.output_schema?.listData || [];
  return Array.isArray(list) ? list : [];
}

/**
 * @param {string} token
 * @param {string} mid
 * @param {string} startYmd
 * @param {string} endYmd
 * @param {number} timeoutMs
 * @param {string|null} appVersion
 */
async function fetchTransactionsForMid(token, mid, startYmd, endYmd, timeoutMs, appVersion, run = null) {
  const all = [];
  let page = 1;
  const size = 200;

  while (page <= 20) {
    const url = buildTransactionUrl(mid, startYmd, endYmd, page, size);
    const json = await getJson(token, url, timeoutMs, {
      appVersion,
      decryptMcb: true,
      afterLogin: true,
    });
    if (run) debug.saveJson(run, `transactions_raw_p${page}`, json);
    const rows = flattenTransactionPayload(json);
    all.push(...rows);

    const paging = json?.output_schema?.paging;
    const next = paging?.next;
    if (!next || rows.length < size) break;
    page = Number(next) || page + 1;
  }

  return all;
}

function isAuthError(err) {
  if (!err) return false;
  const code = err.code || '';
  if (code === 'login_failed' || code === 'token_failed') return true;
  const msg = err instanceof Error ? err.message : String(err);
  if (/api_http_401/i.test(msg)) return true;
  return /invalid_grant|invalid_token|unauthorized|401/i.test(msg);
}

/**
 * @param {import('./qrms-session').QrmsSessionEntry} entry
 * @param {number} timeoutMs
 */
async function refreshAccessToken(entry, timeoutMs) {
  if (!entry.refreshToken) {
    throw new Error('refresh_token_missing');
  }
  if (!entry.hashKey || !entry.xoid) {
    throw new Error('refresh_crypto_missing');
  }
  /** QRMS pakai hash_key + xoid (bukan client_secret OAuth standar). */
  const form = {
    grant_type: 'refresh_token',
    refresh_token: entry.refreshToken,
    hash_key: entry.hashKey,
    xoid: entry.xoid,
  };
  return fetchToken(form, timeoutMs);
}

/**
 * @param {'cache'|'refresh'|'puppeteer'} authMethod
 */
function logAuthMethod(authMethod, extra = '') {
  const tag = authMethod.toUpperCase();
  console.log(`[bca_scrapper][qris] auth=${tag}${extra ? ` ${extra}` : ''}`);
}

/**
 * @param {{
 *   email: string,
 *   password: string,
 *   timeoutMs?: number,
 *   headless?: boolean,
 * }} opts
 * @param {ReturnType<typeof debug.beginRun>} run
 * @param {boolean} forceLogin
 */
async function resolveAuth(opts, run, forceLogin = false) {
  const timeoutMs = Number(opts.timeoutMs || 30000);

  if (!forceLogin) {
    const cached = qrmsSession.getValid(opts.email);
    if (cached) {
      const expiresInSec = Math.max(0, Math.floor((cached.expiresAt - Date.now()) / 1000));
      debug.step(run, 'session_cache_hit', { expires_in_sec: expiresInSec });
      logAuthMethod('cache', `expires_in=${expiresInSec}s`);
      return {
        token: cached.accessToken,
        appVersion: cached.appVersion,
        outlets: Array.isArray(cached.outlets) ? cached.outlets : null,
        authMethod: 'cache',
        releaseBrowser: null,
        dashboardPage: null,
      };
    }

    const stale = qrmsSession.getForRefresh(opts.email);
    if (stale) {
      try {
        debug.step(run, 'session_refresh_start');
        logAuthMethod('refresh');
        const tokenJson = await refreshAccessToken(stale, timeoutMs);
        debug.saveJson(run, 'token_refresh_response', {
          expires_in: tokenJson.expires_in,
          has_refresh_token: Boolean(tokenJson.refresh_token),
          refresh_expires_in: tokenJson.refresh_expires_in,
        });

        const token = tokenJson.access_token;
        qrmsSession.save(opts.email, {
          accessToken: token,
          refreshToken: tokenJson.refresh_token || stale.refreshToken,
          hashKey: stale.hashKey,
          xoid: stale.xoid,
          appVersion: stale.appVersion,
          expiresIn: tokenJson.expires_in,
          refreshExpiresIn: tokenJson.refresh_expires_in,
          outlets: stale.outlets,
        });
        console.log(
          `[bca_scrapper][qris] session=REFRESHED expires_in=${tokenJson.expires_in ?? '?'}s`
        );

        return {
          token,
          appVersion: stale.appVersion,
          outlets: Array.isArray(stale.outlets) ? stale.outlets : null,
          authMethod: 'refresh',
          releaseBrowser: null,
          dashboardPage: null,
        };
      } catch (err) {
        debug.step(run, 'session_refresh_failed', {
          error: err instanceof Error ? err.message : String(err),
        });
        console.warn(
          '[bca_scrapper][qris] refresh=FAILED',
          err instanceof Error ? err.message : err,
          '→ Puppeteer'
        );
        qrmsSession.invalidate(opts.email);
      }
    }
  }

  debug.step(run, forceLogin ? 'session_cache_miss_forced_login' : 'session_cache_miss_puppeteer_login');
  logAuthMethod('puppeteer', forceLogin ? '(retry)' : '');
  const loginPayload = await buildEncryptedLoginPayload(opts.email, opts.password, {
    headless: opts.headless,
    timeoutMs,
    keepBrowser: true,
  });

  const loginForm = loginPayload.loginForm || {};
  debug.saveJson(run, 'login_form_keys', {
    grant_type: loginForm.grant_type,
    has_hash_key: Boolean(loginForm.hash_key),
    has_xoid: Boolean(loginForm.xoid),
    has_app_version: Boolean(loginPayload.appVersion),
  });

  const tokenJson = await fetchToken(loginForm, timeoutMs);
  debug.saveJson(run, 'token_response', {
    token_type: tokenJson.token_type,
    expires_in: tokenJson.expires_in,
    has_refresh_token: Boolean(tokenJson.refresh_token),
    refresh_expires_in: tokenJson.refresh_expires_in,
  });

  const token = tokenJson.access_token;
  const appVersion = loginPayload.appVersion;

  debug.step(run, 'fetch_outlets');
  const outlets = await fetchOutlets(token, timeoutMs, appVersion);
  debug.saveJson(run, 'outlets', outlets);

  qrmsSession.save(opts.email, {
    accessToken: token,
    refreshToken: tokenJson.refresh_token,
    hashKey: loginForm.hash_key,
    xoid: loginForm.xoid,
    appVersion,
    expiresIn: tokenJson.expires_in,
    refreshExpiresIn: tokenJson.refresh_expires_in,
    outlets,
  });
  console.log(
    `[bca_scrapper][qris] session=SAVED expires_in=${tokenJson.expires_in ?? '?'}s`
    + ` refresh=${tokenJson.refresh_token ? 'yes' : 'no'}`
  );

  return {
    token,
    appVersion,
    outlets,
    authMethod: 'puppeteer',
    releaseBrowser: loginPayload.release || null,
    dashboardPage: loginPayload.page || null,
  };
}

/**
 * @param {{
 *   email: string,
 *   password: string,
 *   startDate: string,
 *   endDate: string,
 *   timeoutMs?: number,
 *   headless?: boolean,
 * }} opts
 */
async function fetchQrisTransactionsHttp(opts) {
  const run = debug.beginRun('http-qris-transactions');
  const timeoutMs = Number(opts.timeoutMs || 30000);
  /** @type {(() => Promise<void>) | null} */
  let releaseBrowser = null;

  try {
    for (let attempt = 0; attempt < 2; attempt++) {
      const forceLogin = attempt > 0;
      /** @type {import('puppeteer').Page | null} */
      let dashboardPage = null;
      try {
        const auth = await resolveAuth(opts, run, forceLogin);
        releaseBrowser = auth.releaseBrowser;
        dashboardPage = auth.dashboardPage || null;

        let outlets = auth.outlets;
        if (!outlets || outlets.length === 0) {
          debug.step(run, 'fetch_outlets');
          outlets = await fetchOutlets(auth.token, timeoutMs, auth.appVersion);
          qrmsSession.save(opts.email, {
            accessToken: auth.token,
            appVersion: auth.appVersion,
            outlets,
          });
        }

        if (outlets.length === 0) {
          throw new Error('no_outlets_found');
        }

        let transactions = [];
        let usedDomFallback = false;
        for (const outlet of outlets) {
          const mid = outlet.mid || outlet.outlet_id || outlet.outletId;
          if (!mid) continue;
          debug.step(run, 'fetch_transactions', {
            mid,
            start: opts.startDate,
            end: opts.endDate,
            auth: auth.authMethod,
          });
          const rows = await fetchTransactionsForMid(
            auth.token,
            mid,
            opts.startDate,
            opts.endDate,
            timeoutMs,
            auth.appVersion,
            run
          );
          transactions.push(...rows);
        }

        if (transactions.length === 0 && dashboardPage) {
          debug.step(run, 'dom_fallback_scrape', { start: opts.startDate, end: opts.endDate });
          const domRows = await scrapeTransactionsForDateRange(
            dashboardPage,
            opts.startDate,
            opts.endDate
          );
          debug.saveJson(run, 'transactions_dom', { count: domRows.length, transactions: domRows });
          if (domRows.length > 0) {
            transactions = domRows;
            usedDomFallback = true;
          }
        }

        debug.saveJson(run, 'transactions_parsed', {
          count: transactions.length,
          transactions,
          auth: auth.authMethod,
        });

        transactions = filterByDateRange(transactions, opts.startDate, opts.endDate);

        debug.finishRun(run, {
          ok: true,
          count: transactions.length,
          usedDomFallback,
          auth: auth.authMethod,
        });
        console.log(
          `[bca_scrapper][qris] done auth=${auth.authMethod.toUpperCase()}`
          + ` count=${transactions.length} dom_fallback=${usedDomFallback ? 'yes' : 'no'}`
          + ` range=${opts.startDate}..${opts.endDate}`
        );
        return {
          start: opts.startDate,
          end: opts.endDate,
          transactions,
          used_dom_fallback: usedDomFallback,
          auth_method: auth.authMethod,
          from_cache: auth.authMethod === 'cache' || auth.authMethod === 'refresh',
          outlets: outlets.map((o) => ({
            mid: o.mid,
            name: o.outlet_name || o.outletName,
            type: o.outlet_type || o.outletType,
          })),
        };
      } catch (err) {
        if (attempt === 0 && isAuthError(err)) {
          qrmsSession.invalidate(opts.email);
          debug.step(run, 'session_cache_invalidate_retry');
          console.warn('[bca_scrapper][qris] session=INVALIDATED retrying with Puppeteer');
          if (releaseBrowser) {
            await releaseBrowser().catch(() => {});
            releaseBrowser = null;
          }
          continue;
        }
        throw err;
      } finally {
        if (releaseBrowser) {
          await releaseBrowser().catch(() => {});
          releaseBrowser = null;
        }
      }
    }

    throw new Error('auth_retry_exhausted');
  } catch (err) {
    debug.step(run, 'run_failed', { error: err instanceof Error ? err.message : String(err) });
    debug.finishRun(run, { ok: false, error: err instanceof Error ? err.message : String(err) });
    throw err;
  }
}

module.exports = {
  fetchQrisTransactionsHttp,
  fetchToken,
};
