const isProd = typeof window !== "undefined" && !["localhost", "127.0.0.1"].includes(window.location.hostname);

export const API_BASE = isProd ? "https://api.nalju.com" : "";

export function apiUrl(path) {
  return `${API_BASE}${path}`;
}

export function wsUrl(user) {
  if (!user) return null;
  const base = isProd ? "wss://wadeskserver.nalju.com" : `ws://${window.location.hostname}:3010`;
  const params = new URLSearchParams({
    tenant_id: String(user.tenant_id),
    user_id: String(user.id),
    role: user.role,
  });
  if (user.team_id != null) params.set("team_id", String(user.team_id));
  return `${base}?${params.toString()}`;
}

export async function api(path, { method = "GET", body, token } = {}) {
  const headers = {
    Accept: "application/json",
  };
  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
  }
  const authToken = token ?? localStorage.getItem("wadesk_token");
  if (authToken) {
    headers["X-Wadesk-Token"] = authToken;
  }

  const res = await fetch(apiUrl(path), {
    method,
    headers,
    credentials: "include",
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.status === false) {
    const err = new Error(data.message || `HTTP ${res.status}`);
    err.status = res.status;
    err.data = data.data;
    throw err;
  }
  return data;
}
