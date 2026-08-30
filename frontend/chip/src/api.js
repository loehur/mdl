const isProd = import.meta.env.PROD;

export const API_BASE = isProd ? "https://api.nalju.com" : "";

export function apiUrl(path) {
  return `${API_BASE}${path}`;
}

export async function apiFetch(path, options = {}) {
  return fetch(apiUrl(path), {
    credentials: "include",
    ...options,
    headers: {
      Accept: "application/json",
      ...(options.headers || {}),
    },
  });
}

/**
 * Panggil API dan kembalikan { status, message, data }.
 * Lempar Error(message) bila gagal/HTTP error.
 */
export async function api(path, options = {}) {
  const res = await apiFetch(path, options);
  let body = null;
  try {
    body = await res.json();
  } catch (_) {
    /* ignore */
  }
  if (!res.ok || !body || body.status === false) {
    const msg = body?.message || body?.error || `HTTP ${res.status}`;
    const err = new Error(msg);
    err.status = res.status;
    err.body = body;
    throw err;
  }
  return body;
}
