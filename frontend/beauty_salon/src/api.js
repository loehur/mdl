// API Configuration
// For development with Vite proxy, use empty string
// For production, use the full backend URL

const isProd = import.meta.env.PROD;

export const API_BASE = isProd
    ? '/pribadi/mdl/api'
    : '';

export function apiUrl(path) {
    return `${API_BASE}${path}`;
}

// Helper function for fetch with API base
export async function apiFetch(path, options = {}) {
    return fetch(apiUrl(path), options);
}
