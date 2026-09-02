import { api, session } from './api';
export type NemuSession = { userId: string; tenantId: string; role: string; email: string; displayName?: string; avatarUrl?: string; accessToken: string };
const profileKey = 'nemu_profile';
export function profile() { const raw = localStorage.getItem(profileKey); return raw ? JSON.parse(raw) as Pick<NemuSession, 'email' | 'displayName' | 'avatarUrl'> : undefined; }
export async function loadProfile() { const result = await api<Pick<NemuSession, 'email' | 'displayName' | 'avatarUrl'>>('/v1/me'); localStorage.setItem(profileKey, JSON.stringify(result)); return result; }
export async function signInWithGoogleIdToken(idToken: string) { const result = await api<NemuSession>('/v1/auth/session', { method: 'POST', body: JSON.stringify({ idToken }) }); session.set(result.accessToken); localStorage.setItem(profileKey, JSON.stringify({ email: result.email, displayName: result.displayName, avatarUrl: result.avatarUrl })); return result; }
declare global {
  interface Window { google?: { accounts: { id: { initialize(config: { client_id: string; callback: (response: { credential: string }) => void }): void; renderButton(element: HTMLElement, options: Record<string, string | number>): void; disableAutoSelect(): void } } } }
}
export function loadGoogleIdentityScript(): Promise<void> {
  if (window.google) return Promise.resolve();
  return new Promise((resolve, reject) => { const existing = document.querySelector<HTMLScriptElement>('script[src="https://accounts.google.com/gsi/client"]'); if (existing) { existing.addEventListener('load', () => resolve(), { once: true }); existing.addEventListener('error', () => reject(new Error('Google Sign-In tidak dapat dimuat')), { once: true }); return; } const script = document.createElement('script'); script.src = 'https://accounts.google.com/gsi/client'; script.async = true; script.onload = () => resolve(); script.onerror = () => reject(new Error('Google Sign-In tidak dapat dimuat')); document.head.append(script); });
}
