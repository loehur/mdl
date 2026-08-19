/** @param {string} input */
function stringBetween(input, start, end) {
  if (typeof input !== 'string' || input === '') return '';
  const escapedStart = start.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const escapedEnd = end.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const m = input.match(new RegExp(`${escapedStart}([\\S\\s]*?)${escapedEnd}`));
  return m ? m[1] : '';
}

/** @param {string} input */
function tdValues(input) {
  if (typeof input !== 'string' || input === '') return [];
  const m = input.match(/<td\b[^>]*?>([\s\S]*?)<\/td>/gi);
  return m || [];
}

/** @param {string} input */
function removeHtml(input) {
  if (typeof input !== 'string') return '';
  return input
    .replace(/<[^>]*>?/gm, '')
    .replace(/&nbsp;/gi, ' ')
    .replace(/\u00a0/g, ' ')
    .trim();
}

/** @param {string} input */
function cleanCellText(input) {
  return removeHtml(input).replace(/\s+/g, ' ').trim();
}

/**
 * @param {{ rekening?: string, saldo?: number, accounts?: array }} balance
 */
function isValidBalance(balance) {
  if (!balance) return false;
  const accounts = Array.isArray(balance.accounts) ? balance.accounts : [];
  if (accounts.some((a) => /^\d{5,}$/.test(String(a.rekening || '').replace(/\D/g, '')))) {
    return true;
  }
  const rek = String(balance.rekening || '').replace(/\D/g, '');
  return rek.length >= 5 && Number(balance.saldo) > 0;
}

/** @param {string|number} input */
function toNumber(input) {
  if (typeof input === 'number') return input;
  if (typeof input !== 'string') return 0;
  const cleaned = input.replace(/[^\d.-]/g, '');
  const n = Number(cleaned);
  return Number.isFinite(n) ? n : 0;
}

/**
 * @param {string|Date} value
 * @returns {{ day: string, month: string, year: string, iso: string }}
 */
function parseDateParts(value) {
  let d;
  if (value instanceof Date) {
    d = value;
  } else if (typeof value === 'string' && value.trim() !== '') {
    d = new Date(value.trim());
  } else {
    d = new Date();
  }
  if (Number.isNaN(d.getTime())) {
    throw new Error('invalid_date');
  }
  return {
    day: String(d.getDate()).padStart(2, '0'),
    month: String(d.getMonth() + 1).padStart(2, '0'),
    year: String(d.getFullYear()),
    iso: d.toISOString().slice(0, 10),
  };
}

/** @param {string} input */
function cellMultiline(input) {
  if (typeof input !== 'string') return '';
  const withBreaks = input.replace(/<br\s*\/?>/gi, '\n');
  return removeHtml(withBreaks)
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter(Boolean)
    .join('\n');
}

/**
 * Parse sel "12,474.00 CR" atau "160,320.00 CR" (format ibank 4 kolom).
 * @param {string} input
 * @returns {{ nominal: number, mutasi: string }|null}
 */
function parseAmountAndDirection(input) {
  const text = cleanCellText(input);
  const m = text.match(/^([\d.,]+)\s*(CR|DB)\s*$/i);
  if (!m) return null;
  return {
    nominal: toNumber(m[1]),
    mutasi: m[2].toUpperCase(),
  };
}

/**
 * Parse mutasi ibank desktop (4 kolom: Tgl/Ket/Cab/Mutasi) atau 5–6 kolom dengan Nominal terpisah.
 * @param {string} html
 */
function parseMutasiIbank(html) {
  const rows = [];
  const trRegex = /<tr[^>]*>([\s\S]*?)<\/tr>/gi;
  let trMatch;

  while ((trMatch = trRegex.exec(html)) !== null) {
    const rowHtml = trMatch[0];
    if (/<b>\s*Tgl\./i.test(rowHtml)) continue;

    const rawCells = tdValues(rowHtml);
    if (rawCells.length < 4) continue;

    const tanggal = cleanCellText(rawCells[0]);
    if (!/^PEND$/i.test(tanggal) && !/^\d{2}\/\d{2}\/\d{4}$/.test(tanggal)) {
      continue;
    }

    const keterangan = cellMultiline(rawCells[1]);
    if (!keterangan) continue;

    const cab = cleanCellText(rawCells[2]);
    let nominal = 0;
    let mutasi = '';
    let saldo_akhir = null;

    const dirCell = rawCells.length >= 5 ? cleanCellText(rawCells[4]).toUpperCase() : '';
    if (dirCell === 'CR' || dirCell === 'DB') {
      nominal = toNumber(cleanCellText(rawCells[3]));
      mutasi = dirCell;
      if (rawCells[5]) {
        saldo_akhir = toNumber(cleanCellText(rawCells[5]));
      }
    } else {
      const parsed = parseAmountAndDirection(rawCells[3]);
      if (!parsed) continue;
      nominal = parsed.nominal;
      mutasi = parsed.mutasi;
    }

    if (!mutasi || (mutasi !== 'CR' && mutasi !== 'DB') || nominal <= 0) {
      continue;
    }

    rows.push({
      tanggal,
      keterangan,
      cab,
      nominal,
      mutasi,
      saldo_akhir,
    });
  }

  return rows;
}

/**
 * Parse mutasi mobile m.klikbca.com (format lama).
 * @param {string} html
 */
function parseMutasiMobile(html) {
  const block = stringBetween(html, 'KETERANGAN', '</table>');
  const cells = tdValues(block);
  const cleanStmt = [];

  for (let i = 1; i < cells.length; i += 2) {
    const keteranganRaw = removeHtml(cells[i].split(' ').join('\n'));
    if (!keteranganRaw) continue;

    let keterangan = keteranganRaw.substring(0, Math.max(0, keteranganRaw.length - 2));
    const lines = keterangan.split(/\r?\n/).filter(Boolean);
    const nominal = toNumber(lines.pop() || '0');
    const cab = lines.pop() || '';
    keterangan = lines.join('\n').trim();
    const mutasi = keteranganRaw.slice(-2).trim();

    cleanStmt.push({
      tanggal: removeHtml(cells[i - 1].split(' ').join('\n')),
      keterangan,
      cab,
      nominal,
      mutasi,
    });
  }

  return cleanStmt;
}

/**
 * Parse baris mutasi dari HTML KlikBCA (ibank / mobile).
 * @param {string} html
 * @returns {Array<{ tanggal: string, keterangan: string, cab: string, nominal: number, mutasi: string, saldo_akhir?: number|null }>}
 */
function parseMutasiHtml(html) {
  if (typeof html !== 'string' || html === '') return [];
  if (html.includes('TIDAK ADA TRANSAKSI')) return [];

  // ibank desktop: MUTASI REKENING dengan kolom Tgl./Keterangan
  if (/MUTASI REKENING|<b>\s*Tgl\./i.test(html)) {
    const ibank = parseMutasiIbank(html);
    if (ibank.length > 0) return ibank;
  }

  return parseMutasiMobile(html);
}

/**
 * Parse saldo dari HTML balance inquiry (ibank desktop + mobile).
 * @param {string} html
 */
function parseBalanceHtml(html) {
  if (typeof html !== 'string' || html === '') {
    return { rekening: '', saldo: 0, accounts: [] };
  }

  // ibank desktop: baris data pakai bgcolor #e0e0e0 (header #f0f0f0)
  const accounts = [];
  const trRegex = /<tr[^>]*>([\s\S]*?)<\/tr>/gi;
  let trMatch;
  while ((trMatch = trRegex.exec(html)) !== null) {
    const rowHtml = trMatch[0];
    if (!/bgcolor\s*=\s*["']#e0e0e0["']/i.test(rowHtml)) continue;

    const cells = tdValues(rowHtml).map(cleanCellText).filter((v) => v !== '');
    if (cells.length < 4) continue;

    const rekeningDigits = cells[0].replace(/\D/g, '');
    if (!/^\d{5,}$/.test(rekeningDigits)) continue;

    accounts.push({
      rekening: cells[0],
      jenis: cells[1],
      mata_uang: cells[2],
      saldo: toNumber(cells[3]),
    });
  }

  if (accounts.length > 0) {
    return {
      rekening: accounts[0].rekening,
      saldo: accounts[0].saldo,
      accounts,
    };
  }

  // mobile / format lama
  const mobileSaldo = stringBetween(
    html,
    "<td align='right'><font size='1' color='#0000a7'><b>",
    '</td>'
  );
  if (mobileSaldo) {
    return {
      rekening: cleanCellText(stringBetween(html, '<td align="left">', '</td>')),
      saldo: toNumber(mobileSaldo),
      accounts: [],
    };
  }

  const rekening = cleanCellText(stringBetween(html, '<td align="left">', '</td>'));
  const saldo = toNumber(stringBetween(html, '<td align="right">', '</td>'));
  return { rekening, saldo, accounts: [] };
}

module.exports = {
  stringBetween,
  tdValues,
  removeHtml,
  cleanCellText,
  toNumber,
  parseDateParts,
  parseMutasiHtml,
  parseBalanceHtml,
  isValidBalance,
};
