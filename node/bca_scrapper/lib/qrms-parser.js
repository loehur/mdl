/**
 * Normalisasi baris transaksi QRMS dari JSON API atau DOM.
 */

function parseAmount(value) {
  if (value == null || value === '') return null;
  if (typeof value === 'number' && Number.isFinite(value)) return Math.round(value);
  const s = String(value).replace(/[^\d,-]/g, '').replace(/\./g, '').replace(',', '.');
  const n = Number(s);
  return Number.isFinite(n) ? Math.round(n) : null;
}

function pickString(obj, keys) {
  for (const k of keys) {
    const v = obj[k];
    if (v != null && String(v).trim() !== '') return String(v).trim();
  }
  return '';
}

function normalizeOne(row) {
  if (!row || typeof row !== 'object') return null;

  const tanggal = pickString(row, [
    'tanggal',
    'date',
    'trxDate',
    'transactionDate',
    'tglTransaksi',
    'tgl_trx',
    'tgl',
    'waktuTransaksi',
    'transaction_time',
  ]);
  const waktu = pickString(row, [
    'waktu',
    'time',
    'trxTime',
    'transactionTime',
    'jam',
    'hour',
    'waktuTransaksi',
  ]);
  const nominal =
    parseAmount(row.nominal) ??
    parseAmount(row.amount) ??
    parseAmount(row.trxAmount) ??
    parseAmount(row.transactionAmount) ??
    parseAmount(row.nominalTransaksi) ??
    parseAmount(row.totalAmount) ??
    parseAmount(row.amount_trx);
  const status = pickString(row, [
    'status',
    'trxStatus',
    'transactionStatus',
    'paymentStatus',
    'statusTransaction',
  ]);
  const keterangan = pickString(row, [
    'keterangan',
    'description',
    'desc',
    'merchantName',
    'namaMerchant',
    'customerName',
    'issuerName',
    'issuer_name',
    'issuer',
    'bankName',
    'note',
    'from',
    'transaction_description',
    'trxDescription',
  ]);
  const rrn = pickString(row, [
    'rrn',
    'refNo',
    'referenceNo',
    'referenceNumber',
    'invoiceNo',
    'trxId',
    'id',
    'reference_no',
  ]);

  let tanggalOut = tanggal || null;
  let waktuOut = waktu || null;
  const wt = row.waktuTransaksi != null ? String(row.waktuTransaksi).trim() : '';
  if (wt) {
    const isoDt = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2})?)/.exec(wt);
    if (isoDt) {
      tanggalOut = tanggalOut || isoDt[1];
      waktuOut = waktuOut || isoDt[2];
    } else if (/^\d{1,2}[.:]\d{2}/.test(wt)) {
      waktuOut = waktuOut || wt.replace('.', ':');
    } else if (!tanggalOut) {
      tanggalOut = wt;
    }
  }

  if (!tanggalOut && !waktuOut && nominal == null && !rrn) return null;

  return {
    tanggal: tanggalOut,
    waktu: waktuOut,
    nominal,
    status: status || null,
    keterangan: keterangan || null,
    rrn: rrn || null,
  };
}

function flattenPayload(payload) {
  if (payload == null) return [];
  if (Array.isArray(payload)) return payload;
  if (typeof payload !== 'object') return [];

  const listKeys = [
    'list_data',
    'listData',
    'data',
    'content',
    'items',
    'list',
    'transactions',
    'transactionList',
    'trxList',
    'records',
    'rows',
    'result',
  ];

  for (const key of listKeys) {
    const v = payload[key];
    if (Array.isArray(v)) return v;
    if (v && typeof v === 'object') {
      for (const k2 of listKeys) {
        if (Array.isArray(v[k2])) return v[k2];
      }
    }
  }

  return [];
}

/**
 * @param {unknown} payload
 * @returns {object[]}
 */
function parseTransactionsFromJson(payload) {
  const rows = flattenPayload(payload);
  const out = [];
  for (const row of rows) {
    const norm = normalizeOne(row);
    if (norm) out.push(norm);
  }
  return out;
}

/**
 * @param {unknown[]} apiBodies — JSON body dari intercept network
 * @returns {object[]}
 */
function parseTransactionsFromApiBodies(apiBodies) {
  const all = [];
  for (const body of apiBodies) {
    if (body == null) continue;
    all.push(...parseTransactionsFromJson(body));
  }
  return dedupeTransactions(all);
}

/**
 * Parse tabel HTML di dashboard QRMS (fallback).
 * @param {string} html
 */
function parseTransactionsFromHtml(html) {
  if (!html || typeof html !== 'string') return [];

  const rows = [];
  const trRe = /<tr[^>]*>([\s\S]*?)<\/tr>/gi;
  let trMatch;
  while ((trMatch = trRe.exec(html))) {
    const cells = [];
    const tdRe = /<t[dh][^>]*>([\s\S]*?)<\/t[dh]>/gi;
    let tdMatch;
    while ((tdMatch = tdRe.exec(trMatch[1]))) {
      const text = tdMatch[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      if (text) cells.push(text);
    }
    if (cells.length < 2) continue;

    const joined = cells.join(' | ');
    if (/transaksi|tanggal|nominal|jumlah|status/i.test(joined) && cells.length <= 6) continue;

    const nominalCell = cells.find((c) => /Rp\s*[\d.]/.test(c) || /^[\d.,]+$/.test(c));
    const nominal = nominalCell ? parseAmount(nominalCell) : null;
    const dateCell = cells.find((c) => /\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/.test(c));
    const timeCell = cells.find((c) => /\d{1,2}:\d{2}/.test(c));

    if (!nominal && !dateCell) continue;

    rows.push({
      tanggal: dateCell || null,
      waktu: timeCell || null,
      nominal,
      status: cells.find((c) => /sukses|success|gagal|failed|pending/i.test(c)) || null,
      keterangan: cells.filter((c) => c !== dateCell && c !== timeCell && c !== nominalCell).join(' | ') || null,
      rrn: null,
    });
  }

  return dedupeTransactions(rows);
}

function dedupeTransactions(rows) {
  const seen = new Set();
  const out = [];
  for (const row of rows) {
    const key = [row.tanggal, row.waktu, row.nominal, row.rrn, row.keterangan].join('|');
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(row);
  }
  return out;
}

function ymdToParts(ymd) {
  const [y, m, d] = String(ymd).split('-').map(Number);
  return { y, m, d };
}

function parseRowDate(row) {
  const raw = row.tanggal || row.waktu || '';
  const iso = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
  if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;
  const dmY = /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/.exec(raw);
  if (dmY) {
    let y = Number(dmY[3]);
    if (y < 100) y += 2000;
    const m = String(Number(dmY[2])).padStart(2, '0');
    const d = String(Number(dmY[1])).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
  return null;
}

/**
 * Filter transaksi inklusif YYYY-MM-DD.
 * @param {object[]} rows
 * @param {string} startYmd
 * @param {string} endYmd
 */
function filterByDateRange(rows, startYmd, endYmd) {
  return rows.filter((row) => {
    const ymd = parseRowDate(row);
    if (!ymd) return true;
    return ymd >= startYmd && ymd <= endYmd;
  });
}

module.exports = {
  parseTransactionsFromJson,
  parseTransactionsFromApiBodies,
  parseTransactionsFromHtml,
  filterByDateRange,
  dedupeTransactions,
  ymdToParts,
};
