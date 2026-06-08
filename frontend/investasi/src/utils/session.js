const SESSION_MS = 7 * 24 * 60 * 60 * 1000;
const STORAGE_KEY = "investasi_user";

function readRaw() {
  const raw = localStorage.getItem(STORAGE_KEY);
  if (!raw) return null;

  try {
    return JSON.parse(raw);
  } catch {
    localStorage.removeItem(STORAGE_KEY);
    return null;
  }
}

export function saveSession(user, token = null) {
  const existing = readRaw();
  const data = {
    user,
    expiry: Date.now() + SESSION_MS,
    token: token || existing?.token || null,
  };
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
}

export function extendSession() {
  const parsed = readRaw();
  if (!parsed?.user) return false;

  parsed.expiry = Date.now() + SESSION_MS;
  localStorage.setItem(STORAGE_KEY, JSON.stringify(parsed));
  return true;
}

export function getValidSession() {
  const parsed = readRaw();
  if (parsed?.expiry && Date.now() < parsed.expiry && parsed.user) {
    return parsed;
  }

  if (parsed) {
    localStorage.removeItem(STORAGE_KEY);
  }
  return null;
}

export function getToken() {
  return getValidSession()?.token || null;
}

export function clearSession() {
  localStorage.removeItem(STORAGE_KEY);
}
