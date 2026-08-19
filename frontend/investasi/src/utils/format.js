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
  return localDateISO();
}

export function localDateISO(date = new Date()) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

export function isToday(dateStr) {
  return !!dateStr && dateStr === localDateISO();
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

/** Nilai portfolio dibagi 1 juta, dengan separator + suffix M (untuk chart). */
export function formatChartMillion(value) {
  const num = Number(value) / 1_000_000;
  if (!Number.isFinite(num)) return "";
  const formatted = new Intl.NumberFormat("id-ID", {
    minimumFractionDigits: 0,
    maximumFractionDigits: num >= 100 ? 0 : 1,
  }).format(num);
  return `${formatted}M`;
}

/** Sumbu Y chart: nilai sudah dalam juta. */
export function formatChartMillionAxis(valueInMillions) {
  const num = Number(valueInMillions);
  if (!Number.isFinite(num)) return "";
  const formatted = new Intl.NumberFormat("id-ID", {
    minimumFractionDigits: 0,
    maximumFractionDigits: num >= 100 ? 0 : 1,
  }).format(num);
  return `${formatted}M`;
}

export function gainLossLabel(status) {
  if (status === "profit") return "Tumbuh";
  if (status === "loss") return "Rugi";
  if (status === "breakeven") return "Impas";
  return null;
}
