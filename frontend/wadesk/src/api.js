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

export async function api(path, { method = "GET", body, token, cache, timeout = 60000 } = {}) {
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

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);

  try {
    const res = await fetch(apiUrl(path), {
      method,
      headers,
      credentials: "include",
      cache: cache ?? "default",
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.status === false) {
      const err = new Error(data.message || `HTTP ${res.status}`);
      err.status = res.status;
      err.data = data.data;
      throw err;
    }
    return data;
  } catch (e) {
    if (e.name === "AbortError") {
      const err = new Error("Request timeout — server tidak merespons. Coba lagi.");
      err.status = 0;
      err.timeout = true;
      throw err;
    }
    throw e;
  } finally {
    clearTimeout(timer);
  }
}

/** Eligible templates for a channel — always fresh (no browser cache). */
export async function fetchEligibleTemplates(channelId = null) {
  const params = new URLSearchParams();
  const cid = Number(channelId);
  if (cid > 0) params.set("channel_id", String(cid));
  params.set("_", String(Date.now()));
  const res = await api(`/WaDesk/Templates/list?${params}`, { cache: "no-store" });
  return res.data?.templates ?? [];
}
