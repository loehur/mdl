export function formatRupiah(value) {
  const num = Number(value) || 0;
  return new Intl.NumberFormat("id-ID", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(num);
}

/** Tampilan "Rp 100.000" — pakai non-breaking space agar tidak wrap. */
export function formatRupiahDisplay(value) {
  return `Rp\u00A0${formatRupiah(value)}`;
}

/** Format USD pedoman, mis. $25.00 */
export function formatUsd(value) {
  const num = Number(value) || 0;
  return new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
}

export function formatUsdDisplay(value) {
  return `$\u00A0${formatUsd(value)}`;
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

/** Parse harga USD (boleh desimal) dari string input. */
export function usdInputToNumber(value) {
  const raw = String(value ?? "").replace(/,/g, ".").replace(/[^\d.]/g, "");
  const num = Number(raw);
  return Number.isFinite(num) ? num : 0;
}

/** Normalisasi nilai dari API ke string digit untuk form. */
export function toAmountDigits(value) {
  const num = Math.round(Number(value) || 0);
  return num > 0 ? String(num) : "";
}

export function toUsdInput(value) {
  const num = Number(value);
  if (!Number.isFinite(num) || num <= 0) return "";
  return String(num);
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

/** Tambah N hari ke tanggal ISO (YYYY-MM-DD), hasil lokal. */
export function addDaysISO(isoDate, days) {
  const date = new Date(`${isoDate}T00:00:00`);
  if (Number.isNaN(date.getTime())) return isoDate;
  date.setDate(date.getDate() + Number(days || 0));
  return localDateISO(date);
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

export function gainLossLabel(status) {
  if (status === "profit") return "Tumbuh";
  if (status === "loss") return "Rugi";
  if (status === "breakeven") return "Impas";
  return null;
}
