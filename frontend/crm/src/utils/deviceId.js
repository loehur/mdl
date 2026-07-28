/**
 * Stable device ID for CRM device-lock.
 * Android WebView: prefer native SharedPreferences ID.
 * Browser: localStorage UUID (persists per origin/profile).
 */
const STORAGE_KEY = "crm_device_id";

function createUuid() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

export function getDeviceId() {
  // Native Android bridge (stable across WebView cache clears)
  try {
    if (window.Android && typeof window.Android.getDeviceId === "function") {
      const nativeId = String(window.Android.getDeviceId() || "").trim();
      if (nativeId) {
        localStorage.setItem(STORAGE_KEY, nativeId);
        return nativeId;
      }
    }
  } catch (e) {
    console.warn("Android.getDeviceId failed", e);
  }

  let id = localStorage.getItem(STORAGE_KEY);
  if (!id) {
    id = createUuid();
    localStorage.setItem(STORAGE_KEY, id);
  }
  return id;
}
