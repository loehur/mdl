const MAX_RANGE_DAYS = Number(process.env.MUTASI_MAX_RANGE_DAYS || 6);
const MAX_LOOKBACK_DAYS = Number(process.env.MUTASI_MAX_LOOKBACK_DAYS || 30);
const QRIS_MAX_RANGE_DAYS = Number(process.env.QRIS_MAX_RANGE_DAYS || 2);
const QRIS_MAX_LOOKBACK_DAYS = Number(process.env.QRIS_MAX_LOOKBACK_DAYS || 30);
const TZ = process.env.TZ || 'Asia/Jakarta';

function todayYmd() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: TZ }).format(new Date());
}

function parseYmd(value) {
  const s = String(value || '').trim();
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
  if (!m) {
    return null;
  }

  const year = Number(m[1]);
  const month = Number(m[2]);
  const day = Number(m[3]);
  const probe = new Date(Date.UTC(year, month - 1, day));
  if (
    probe.getUTCFullYear() !== year ||
    probe.getUTCMonth() !== month - 1 ||
    probe.getUTCDate() !== day
  ) {
    return null;
  }

  return s;
}

function addDaysYmd(ymd, days) {
  const [y, m, d] = ymd.split('-').map(Number);
  const dt = new Date(Date.UTC(y, m - 1, d + days));
  return dt.toISOString().slice(0, 10);
}

function daysBetweenYmd(startYmd, endYmd) {
  const startMs = Date.parse(`${startYmd}T00:00:00Z`);
  const endMs = Date.parse(`${endYmd}T00:00:00Z`);
  return Math.round((endMs - startMs) / 86400000);
}

/**
 * Validasi rentang mutasi:
 * - end_date <= hari ini (zona TZ)
 * - rentang maksimum MAX_RANGE_DAYS hari
 * - start_date tidak lebih dari MAX_LOOKBACK_DAYS ke belakang
 *
 * @param {string|null|undefined} startDate
 * @param {string|null|undefined} endDate
 * @returns {{ startDate: string, endDate: string }}
 */
function validateMutasiDateRange(startDate, endDate) {
  const today = todayYmd();

  if (startDate != null && String(startDate).trim() !== '' && !parseYmd(startDate)) {
    const err = new Error('start_date tidak valid (gunakan YYYY-MM-DD)');
    err.code = 'invalid_date';
    throw err;
  }
  if (endDate != null && String(endDate).trim() !== '' && !parseYmd(endDate)) {
    const err = new Error('end_date tidak valid (gunakan YYYY-MM-DD)');
    err.code = 'invalid_date';
    throw err;
  }

  const start = parseYmd(startDate) || today;
  const end = parseYmd(endDate) || start;

  if (end > today) {
    const err = new Error('end_date tidak boleh melebihi hari ini');
    err.code = 'end_date_future';
    throw err;
  }

  if (start > end) {
    const err = new Error('start_date tidak boleh setelah end_date');
    err.code = 'invalid_date';
    throw err;
  }

  const minStart = addDaysYmd(today, -MAX_LOOKBACK_DAYS);
  if (start < minStart) {
    const err = new Error(`start_date tidak boleh lebih dari ${MAX_LOOKBACK_DAYS} hari yang lalu`);
    err.code = 'start_date_too_old';
    throw err;
  }

  const rangeDays = daysBetweenYmd(start, end) + 1;
  if (rangeDays > MAX_RANGE_DAYS) {
    const err = new Error(`rentang tanggal maksimum ${MAX_RANGE_DAYS} hari`);
    err.code = 'date_range_too_large';
    throw err;
  }

  return { startDate: start, endDate: end };
}

/**
 * Validasi tanggal transaksi QRIS — rentang maks 2 hari, lookback max 30 hari (sama BCA).
 * @param {string|null|undefined} startDate
 * @param {string|null|undefined} endDate
 * @returns {{ startDate: string, endDate: string }}
 */
function validateQrisDateRange(startDate, endDate) {
  const today = todayYmd();

  if (startDate != null && String(startDate).trim() !== '' && !parseYmd(startDate)) {
    const err = new Error('start_date tidak valid (gunakan YYYY-MM-DD)');
    err.code = 'invalid_date';
    throw err;
  }
  if (endDate != null && String(endDate).trim() !== '' && !parseYmd(endDate)) {
    const err = new Error('end_date tidak valid (gunakan YYYY-MM-DD)');
    err.code = 'invalid_date';
    throw err;
  }

  const start = parseYmd(startDate) || today;
  const end = parseYmd(endDate) || start;

  if (end > today) {
    const err = new Error('end_date tidak boleh melebihi hari ini');
    err.code = 'end_date_future';
    throw err;
  }

  if (start > end) {
    const err = new Error('start_date tidak boleh setelah end_date');
    err.code = 'invalid_date';
    throw err;
  }

  const minStart = addDaysYmd(today, -QRIS_MAX_LOOKBACK_DAYS);
  if (start < minStart) {
    const err = new Error(`start_date tidak boleh lebih dari ${QRIS_MAX_LOOKBACK_DAYS} hari yang lalu`);
    err.code = 'start_date_too_old';
    throw err;
  }

  const rangeDays = daysBetweenYmd(start, end) + 1;
  if (rangeDays > QRIS_MAX_RANGE_DAYS) {
    const err = new Error(`rentang QRIS maksimum ${QRIS_MAX_RANGE_DAYS} hari`);
    err.code = 'date_range_too_large';
    throw err;
  }

  return { startDate: start, endDate: end };
}

module.exports = {
  validateMutasiDateRange,
  validateQrisDateRange,
  todayYmd,
  addDaysYmd,
  daysBetweenYmd,
  MAX_RANGE_DAYS,
  MAX_LOOKBACK_DAYS,
  QRIS_MAX_RANGE_DAYS,
  QRIS_MAX_LOOKBACK_DAYS,
  TZ,
};
