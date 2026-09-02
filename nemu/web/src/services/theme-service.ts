const key = 'nemu_theme';
export type Theme = 'light' | 'dark';
export function applyTheme(theme: Theme) { document.documentElement.classList.toggle('dark', theme === 'dark'); localStorage.setItem(key, theme); }
export function initializeTheme() { const stored = localStorage.getItem(key) as Theme | null; applyTheme(stored ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')); }
export function currentTheme(): Theme { return document.documentElement.classList.contains('dark') ? 'dark' : 'light'; }
