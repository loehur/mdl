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
