const SESSION_MS = 7 * 24 * 60 * 60 * 1000;

export function saveSession(user) {
  localStorage.setItem(
    "investasi_user",
    JSON.stringify({
      user,
      expiry: Date.now() + SESSION_MS,
    })
  );
}

export function extendSession() {
  const raw = localStorage.getItem("investasi_user");
  if (!raw) return false;

  try {
    const parsed = JSON.parse(raw);
    if (!parsed?.user) return false;
    parsed.expiry = Date.now() + SESSION_MS;
    localStorage.setItem("investasi_user", JSON.stringify(parsed));
    return true;
  } catch {
    localStorage.removeItem("investasi_user");
    return false;
  }
}

export function getValidSession() {
  const raw = localStorage.getItem("investasi_user");
  if (!raw) return null;

  try {
    const parsed = JSON.parse(raw);
    if (parsed?.expiry && Date.now() < parsed.expiry && parsed.user) {
      return parsed;
    }
  } catch {
    /* invalid */
  }

  localStorage.removeItem("investasi_user");
  return null;
}

export function clearSession() {
  localStorage.removeItem("investasi_user");
}
