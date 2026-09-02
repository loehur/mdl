declare global { interface Window { NEMU_CONFIG?: { apiBaseUrl?: string; googleClientId?: string } } }
const config = window.NEMU_CONFIG ?? {};
export const apiBaseUrl = config.apiBaseUrl || '/api';
export const googleClientId = config.googleClientId || '';
const tokenKey = 'nemu_access_token';
export const session = { get: () => localStorage.getItem(tokenKey), set: (token: string) => localStorage.setItem(tokenKey, token), clear: () => localStorage.removeItem(tokenKey) };
export class ApiError extends Error { public constructor(message: string, public readonly status: number) { super(message); } }
export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = session.get();
  const response = await fetch(`${apiBaseUrl}${path}`, { ...options, headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(token ? { Authorization: `Bearer ${token}` } : {}), ...options.headers } });
  const body = await response.json().catch(() => ({})) as { data?: T; error?: string };
  if (!response.ok) { if (response.status === 401) session.clear(); throw new ApiError(body.error ?? 'Permintaan gagal', response.status); }
  return body.data as T;
}
