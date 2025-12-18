// API Configuration
// For development with Vite proxy, use empty string
// For production, use the full backend URL

const isProd = import.meta.env.PROD;

export const API_BASE = isProd
    ? 'https://api.nalju.com'
    : '';

export function apiUrl(path) {
    return `${API_BASE}${path}`;
}
