const isProd = import.meta.env.PROD;

export const API_BASE = isProd ? "https://api.nalju.com" : "";

export function apiUrl(path) {
  return `${API_BASE}${path}`;
}
