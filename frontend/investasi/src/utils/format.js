export function formatRupiah(value) {
  const num = Number(value) || 0;
  return new Intl.NumberFormat("id-ID", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(num);
}

/** Ambil digit saja dari input jumlah. */
export function parseAmountInput(value) {
  return String(value ?? "").replace(/\D/g, "");
}

/** Format digit input dengan pemisah ribuan titik, mis. 1000000 → 1.000.000 */
export function formatAmountInput(value) {
  const digits = parseAmountInput(value);
  if (!digits) return "";
  return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

/** Konversi nilai input ke number untuk API. */
export function amountInputToNumber(value) {
  const num = Number(parseAmountInput(value));
  return Number.isFinite(num) ? num : 0;
}

/** Normalisasi nilai dari API ke string digit untuk form. */
export function toAmountDigits(value) {
  const num = Math.round(Number(value) || 0);
  return num > 0 ? String(num) : "";
}

export function formatDate(value) {
  if (!value) return "-";
  const date = new Date(value + "T00:00:00");
  return new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(date);
}

export function todayISO() {
  return new Date().toISOString().slice(0, 10);
}

export function currentMonth() {
  return new Date().toISOString().slice(0, 7);
}

/** Selisih portfolio vs modal investasi: +... atau -... */
export function formatGainLoss(value) {
  if (value === null || value === undefined) return null;
  const num = Number(value);
  if (Number.isNaN(num)) return null;
  const abs = formatRupiah(Math.abs(num));
  if (num > 0) return `+${abs}`;
  if (num < 0) return `-${abs}`;
  return abs;
}

export function gainLossLabel(status) {
  if (status === "profit") return "Tumbuh";
  if (status === "loss") return "Rugi";
  if (status === "breakeven") return "Impas";
  return null;
}
