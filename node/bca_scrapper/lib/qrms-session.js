/**
 * Cache OAuth token QRMS — access_token + refresh_token.
 */

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const log = require('./log');

const CACHE_DIR = path.join(__dirname, '..', '.cache');
const CACHE_FILE = path.join(CACHE_DIR, 'qrms-session.json');

const DEFAULT_BUFFER_SEC = 120;
const DEFAULT_MAX_TTL_SEC = 55 * 60;
const DEFAULT_FALLBACK_TTL_SEC = 10 * 60;
const DEFAULT_REFRESH_FALLBACK_TTL_SEC = 24 * 60 * 60;

/** @type {Record<string, QrmsSessionEntry>} */
let store = {};

/**
 * @typedef {{
 *   accessToken: string,
 *   refreshToken?: string,
 *   hashKey?: string,
 *   xoid?: string,
 *   appVersion: string|null,
 *   browserCookies?: Array<Record<string, unknown>>,
 *   browserStorage?: Record<string, string>,
 *   expiresAt: number,
 *   refreshExpiresAt?: number,
 *   outlets?: Array<Record<string, unknown>>,
 *   savedAt: string,
 * }} QrmsSessionEntry
 */

function isEnabled() {
  const raw = String(process.env.QRMS_SESSION_CACHE || 'true').trim().toLowerCase();
  return raw !== '0' && raw !== 'false' && raw !== 'no';
}

function isRefreshEnabled() {
  const raw = String(process.env.QRMS_REFRESH_TOKEN || 'true').trim().toLowerCase();
  return isEnabled() && raw !== '0' && raw !== 'false' && raw !== 'no';
}

function bufferMs() {
  const sec = Number(process.env.QRMS_SESSION_BUFFER_SEC || DEFAULT_BUFFER_SEC);
  return Number.isFinite(sec) && sec >= 0 ? Math.floor(sec * 1000) : DEFAULT_BUFFER_SEC * 1000;
}

function maxTtlMs() {
  const sec = Number(process.env.QRMS_SESSION_MAX_TTL_SEC || DEFAULT_MAX_TTL_SEC);
  return Number.isFinite(sec) && sec > 0 ? Math.floor(sec * 1000) : DEFAULT_MAX_TTL_SEC * 1000;
}

function maxRefreshTtlMs() {
  const sec = Number(process.env.QRMS_REFRESH_MAX_TTL_SEC || DEFAULT_REFRESH_FALLBACK_TTL_SEC);
  return Number.isFinite(sec) && sec > 0 ? Math.floor(sec * 1000) : DEFAULT_REFRESH_FALLBACK_TTL_SEC * 1000;
}

function emailKey(email) {
  return crypto
    .createHash('sha256')
    .update(String(email || '').trim().toLowerCase())
    .digest('hex')
    .slice(0, 20);
}

function computeExpiresAt(expiresInSec, maxMs = maxTtlMs(), fallbackSec = DEFAULT_FALLBACK_TTL_SEC) {
  const now = Date.now();
  let ttlMs = fallbackSec * 1000;
  const parsed = Number(expiresInSec);
  if (Number.isFinite(parsed) && parsed > 0) {
    ttlMs = Math.floor(parsed * 1000);
  }
  ttlMs = Math.min(ttlMs, maxMs);
  return now + ttlMs;
}

function loadStore() {
  if (!isEnabled()) {
    store = {};
    return;
  }
  try {
    if (!fs.existsSync(CACHE_FILE)) {
      store = {};
      return;
    }
    const raw = JSON.parse(fs.readFileSync(CACHE_FILE, 'utf8'));
    store = raw && typeof raw === 'object' ? raw : {};
  } catch {
    store = {};
  }
}

function persistStore() {
  if (!isEnabled()) return;
  try {
    fs.mkdirSync(CACHE_DIR, { recursive: true, mode: 0o700 });
    fs.writeFileSync(CACHE_FILE, JSON.stringify(store, null, 0), { mode: 0o600 });
  } catch (err) {
    log.warn('[qrms-session] persist failed:', err instanceof Error ? err.message : err);
  }
}

function isAccessValid(entry) {
  if (!entry || typeof entry.accessToken !== 'string' || !entry.accessToken) {
    return false;
  }
  return Date.now() + bufferMs() < Number(entry.expiresAt || 0);
}

function isRefreshValid(entry) {
  if (!isRefreshEnabled()) return false;
  if (!entry || typeof entry.refreshToken !== 'string' || !entry.refreshToken) {
    return false;
  }
  if (!entry.refreshExpiresAt) {
    return true;
  }
  return Date.now() + bufferMs() < Number(entry.refreshExpiresAt);
}

/**
 * @param {string} email
 * @returns {QrmsSessionEntry|null}
 */
function getValid(email) {
  if (!isEnabled()) return null;
  const key = emailKey(email);
  const entry = store[key];
  if (!isAccessValid(entry)) {
    return null;
  }
  return entry;
}

/**
 * Entry untuk refresh — access expired tapi refresh_token masih valid.
 * @param {string} email
 * @returns {QrmsSessionEntry|null}
 */
function getForRefresh(email) {
  if (!isRefreshEnabled()) return null;
  const key = emailKey(email);
  const entry = store[key];
  if (!entry || isAccessValid(entry)) {
    return null;
  }
  if (!isRefreshValid(entry)) {
    delete store[key];
    persistStore();
    return null;
  }
  if (!entry.hashKey || !entry.xoid) {
    return null;
  }
  return entry;
}

/**
 * @param {string} email
 * @param {{
 *   accessToken: string,
 *   refreshToken?: string,
 *   hashKey?: string,
 *   xoid?: string,
 *   appVersion?: string|null,
 *   browserCookies?: Array<Record<string, unknown>>,
 *   browserStorage?: Record<string, string>,
 *   expiresIn?: number,
 *   refreshExpiresIn?: number,
 *   outlets?: Array<Record<string, unknown>>,
 * }} data
 */
function save(email, data) {
  if (!isEnabled()) return;
  const key = emailKey(email);
  const prev = store[key];
  const token = data.accessToken || prev?.accessToken;
  if (!token) return;

  const tokenChanged = Boolean(data.accessToken && data.accessToken !== prev?.accessToken);
  const refreshExpiry = tokenChanged || data.expiresIn != null;
  const expiresAt = refreshExpiry
    ? computeExpiresAt(data.expiresIn)
    : (prev?.expiresAt || computeExpiresAt(data.expiresIn));

  let refreshExpiresAt = prev?.refreshExpiresAt;
  if (data.refreshExpiresIn != null) {
    refreshExpiresAt = computeExpiresAt(
      data.refreshExpiresIn,
      maxRefreshTtlMs(),
      DEFAULT_REFRESH_FALLBACK_TTL_SEC
    );
  } else if (data.refreshToken && data.refreshToken !== prev?.refreshToken) {
    refreshExpiresAt = computeExpiresAt(
      undefined,
      maxRefreshTtlMs(),
      DEFAULT_REFRESH_FALLBACK_TTL_SEC
    );
  }

  store[key] = {
    accessToken: token,
    refreshToken: data.refreshToken !== undefined ? data.refreshToken : prev?.refreshToken,
    hashKey: data.hashKey !== undefined ? data.hashKey : prev?.hashKey,
    xoid: data.xoid !== undefined ? data.xoid : prev?.xoid,
    appVersion: data.appVersion !== undefined ? data.appVersion : (prev?.appVersion ?? null),
    browserCookies: data.browserCookies !== undefined ? data.browserCookies : prev?.browserCookies,
    browserStorage: data.browserStorage !== undefined ? data.browserStorage : prev?.browserStorage,
    expiresAt,
    refreshExpiresAt,
    outlets: Array.isArray(data.outlets) ? data.outlets : prev?.outlets,
    savedAt: new Date().toISOString(),
  };
  persistStore();
}

/**
 * @param {string} email
 */
function invalidate(email) {
  const key = emailKey(email);
  if (!store[key]) return;
  delete store[key];
  persistStore();
}

function invalidateAll() {
  store = {};
  try {
    if (fs.existsSync(CACHE_FILE)) fs.unlinkSync(CACHE_FILE);
  } catch {
    /* ignore */
  }
}

/**
 * @returns {{
 *   enabled: boolean,
 *   refresh_enabled: boolean,
 *   cached: boolean,
 *   access_valid?: boolean,
 *   refresh_available?: boolean,
 *   expires_in_sec?: number,
 *   refresh_expires_in_sec?: number,
 *   saved_at?: string,
 * }}
 */
function status(email) {
  if (!isEnabled()) {
    return { enabled: false, refresh_enabled: false, cached: false };
  }
  const entry = email ? store[emailKey(email)] : Object.values(store)[0];
  if (!entry) {
    return { enabled: true, refresh_enabled: isRefreshEnabled(), cached: false };
  }

  const accessValid = isAccessValid(entry);
  const refreshValid = isRefreshValid(entry);

  return {
    enabled: true,
    refresh_enabled: isRefreshEnabled(),
    cached: accessValid,
    access_valid: accessValid,
    refresh_available: refreshValid && Boolean(entry.refreshToken),
    browser_state: Boolean(entry.browserCookies?.length && entry.browserStorage),
    expires_in_sec: accessValid
      ? Math.max(0, Math.floor((entry.expiresAt - Date.now()) / 1000))
      : 0,
    refresh_expires_in_sec: entry.refreshExpiresAt
      ? Math.max(0, Math.floor((entry.refreshExpiresAt - Date.now()) / 1000))
      : undefined,
    saved_at: entry.savedAt,
  };
}

loadStore();

module.exports = {
  getValid,
  getForRefresh,
  save,
  invalidate,
  invalidateAll,
  status,
  isEnabled,
  isRefreshEnabled,
};
