/**
 * Classify WebSocket close code 1008 from wa_server / CRM Auth verify.
 * @returns {'device_lock'|'lock_race'|'unauthorized'|'verify_failed'|'config_error'|'retry'}
 */
export function classifyWsClose1008(reason) {
  const r = String(reason || "").toLowerCase();

  if (
    r.includes("dikunci di device lain") ||
    r.includes("device lain") ||
    r.includes("device locked")
  ) {
    return "device_lock";
  }

  if (r.includes("belum login") || r.includes("lock tidak ada")) {
    return "lock_race";
  }

  if (r.includes("unauthorized")) {
    return "unauthorized";
  }

  if (r.includes("verify")) {
    return "verify_failed";
  }

  if (r.includes("required")) {
    return "config_error";
  }

  return "retry";
}

export function wsClose1008Message(reason, kind) {
  const raw = String(reason || "").trim();
  if (raw) return raw;

  switch (kind) {
    case "device_lock":
      return "ID dikunci di device lain. Logout dari device tersebut terlebih dahulu.";
    case "lock_race":
      return "Menghubungkan ulang…";
    case "unauthorized":
      return "Username belum terdaftar di WA Server. Coba login ulang atau hubungi admin.";
    case "verify_failed":
      return "Verifikasi device gagal. Coba lagi…";
    case "config_error":
      return "Konfigurasi koneksi tidak lengkap.";
    default:
      return "Koneksi ditolak server. Mencoba lagi…";
  }
}
