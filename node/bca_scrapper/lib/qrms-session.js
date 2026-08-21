/**
 * Cache OAuth token QRMS — skip Puppeteer jika session masih valid.
 */

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const CACHE_DIR = path.join(__dirname, '..', '.cache');
const CACHE_FILE = path.join(CACHE_DIR, 'qrms-session.json');

const DEFAULT_BUFFER_SEC = 120;
const DEFAULT_MAX_TTL_SEC = 55 * 60; // QRMS token biasanya ~1 jam
const DEFAULT_FALLBACK_TTL_SEC = 10 * 60;

/** @type {Record<string, QrmsSessionEntry>} */
let store = {};

/**
 * @typedef {{
 *   accessToken: string,
 *   appVersion: string|null,
 *   expiresAt: number,
 *   outlets?: Array<Record<string, unknown>>,
 *   savedAt: string,
 * }} QrmsSessionEntry
 */

function isEnabled() {
  const raw = String(process.env.QRMS_SESSION_CACHE || 'true').trim().toLowerCase();
  return raw !== '0' && raw !== 'false' && raw !== 'no';
}

function bufferMs() {
  const sec = Number(process.env.QRMS_SESSION_BUFFER_SEC || DEFAULT_BUFFER_SEC);
  return Number.isFinite(sec) && sec >= 0 ? Math.floor(sec * 1000) : DEFAULT_BUFFER_SEC * 1000;
}

function maxTtlMs() {
  const sec = Number(process.env.QRMS_SESSION_MAX_TTL_SEC || DEFAULT_MAX_TTL_SEC);
  return Number.isFinite(sec) && sec > 0 ? Math.floor(sec * 1000) : DEFAULT_MAX_TTL_SEC * 1000;
}

function emailKey(email) {
  return crypto
    .createHash('sha256')
    .update(String(email || '').trim().toLowerCase())
    .digest('hex')
    .slice(0, 20);
}

function computeExpiresAt(expiresInSec) {
  const now = Date.now();
  let ttlMs = DEFAULT_FALLBACK_TTL_SEC * 1000;
  const parsed = Number(expiresInSec);
  if (Number.isFinite(parsed) && parsed > 0) {
    ttlMs = Math.floor(parsed * 1000);
  }
  ttlMs = Math.min(ttlMs, maxTtlMs());
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
    console.warn('[qrms-session] persist failed:', err instanceof Error ? err.message : err);
  }
}

function isValidEntry(entry) {
  if (!entry || typeof entry.accessToken !== 'string' || !entry.accessToken) {
    return false;
  }
  return Date.now() + bufferMs() < Number(entry.expiresAt || 0);
}

/**
 * @param {string} email
 * @returns {QrmsSessionEntry|null}
 */
function getValid(email) {
  if (!isEnabled()) return null;
  const key = emailKey(email);
  const entry = store[key];
  if (!isValidEntry(entry)) {
    if (entry) delete store[key];
    return null;
  }
  return entry;
}

/**
 * @param {string} email
 * @param {{
 *   accessToken: string,
 *   appVersion?: string|null,
 *   expiresIn?: number,
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

  store[key] = {
    accessToken: token,
    appVersion: data.appVersion !== undefined ? data.appVersion : (prev?.appVersion ?? null),
    expiresAt,
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
 * @returns {{ enabled: boolean, cached: boolean, expires_in_sec?: number, saved_at?: string }}
 */
function status(email) {
  if (!isEnabled()) {
    return { enabled: false, cached: false };
  }
  const entry = email ? store[emailKey(email)] : Object.values(store)[0];
  if (!entry || !isValidEntry(entry)) {
    return { enabled: true, cached: false };
  }
  return {
    enabled: true,
    cached: true,
    expires_in_sec: Math.max(0, Math.floor((entry.expiresAt - Date.now()) / 1000)),
    saved_at: entry.savedAt,
  };
}

loadStore();

module.exports = {
  getValid,
  save,
  invalidate,
  invalidateAll,
  status,
  isEnabled,
};
