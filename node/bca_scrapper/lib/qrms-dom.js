/**
 * Ekstrak transaksi dari DOM dashboard QRMS (setelah Angular render).
 * Dipakai saat respons API transaksi terenkripsi MCB.
 */

const { addDaysYmd, daysBetweenYmd } = require('./date-range');

const ID_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function ymdParts(ymd) {
  const [y, m, d] = String(ymd).split('-').map(Number);
  return { y, m, d, monthAbbr: ID_MONTHS[m - 1], day: String(d) };
}

/**
 * Klik tanggal di strip kalender horizontal dashboard.
 * @param {import('puppeteer').Page} page
 * @param {string} ymd YYYY-MM-DD
 * @returns {Promise<boolean>}
 */
async function selectDashboardDate(page, ymd) {
  const { day, monthAbbr } = ymdParts(ymd);
  const label = `${day} ${monthAbbr}`;

  const clicked = await page.evaluate(
    ({ day, monthAbbr }) => {
      const target = `${day}${monthAbbr}`;
      const buttons = [...document.querySelectorAll('button.button-blue, li.ng-star-inserted button, button.button')];
      const match = buttons.find((el) => {
        const t = (el.textContent || '').replace(/\s+/g, '');
        return t.includes(target);
      });
      if (match) {
        match.click();
        return match.textContent.replace(/\s+/g, ' ').trim();
      }
      return null;
    },
    { day, monthAbbr, label }
  );

  if (clicked) {
    await sleep(2500);
    return true;
  }

  return false;
}

function pageHasNoTransactions(text) {
  return /transaksi tidak ada/i.test(text) || /TOTAL TRANSAKSI[^\n]*\(\s*0\s*\)/i.test(text);
}

/**
 * @param {import('puppeteer').Page} page
 * @returns {Promise<object[]>}
 */
async function scrapeTransactionsFromDom(page) {
  const bodyText = await page.evaluate(() => document.body?.innerText || '');
  if (pageHasNoTransactions(bodyText)) return [];

  return page.evaluate(() => {
    /** @type {object[]} */
    const out = [];

    const parseAmount = (s) => {
      if (!s) return null;
      const n = Number(String(s).replace(/\./g, '').replace(/,/g, ''));
      return Number.isFinite(n) ? n : null;
    };

    const text = document.body?.innerText || '';
    const blocks = text.split(/(?=RRN:\s*)/i).slice(1);
    for (const block of blocks) {
      const rrnMatch = /RRN:\s*(\S+)\s*\|\s*([\d.:]+)\s*WIB/i.exec(block);
      const payMatch = /Menerima pembayaran dari\s+(.+?)(?:\n|$)/i.exec(block);
      const amtMatch = /\+\s*Rp\s*([\d.]+)/i.exec(block);
      if (!rrnMatch && !amtMatch) continue;
      out.push({
        tanggal: null,
        waktu: rrnMatch ? rrnMatch[2].replace('.', ':') : null,
        nominal: amtMatch ? parseAmount(amtMatch[1]) : null,
        status: 'sukses',
        keterangan: payMatch ? `Menerima pembayaran dari ${payMatch[1].trim()}` : null,
        rrn: rrnMatch ? rrnMatch[1] : null,
      });
    }

    if (out.length > 0) return out.filter((row) => row.rrn || row.nominal != null);

    const rows = document.querySelectorAll(
      '[class*="transaction"], [class*="trx"], .list-group-item, li.item, .card-transaction'
    );
    for (const row of rows) {
      const t = row.textContent?.replace(/\s+/g, ' ').trim() || '';
      if (!/RRN:/i.test(t) && !/Rp\s*[\d.]/i.test(t)) continue;
      const rrnMatch = /RRN:\s*(\S+)/i.exec(t);
      const timeMatch = /(\d{1,2}[.:]\d{2})\s*WIB/i.exec(t);
      const amtMatch = /\+\s*Rp\s*([\d.]+)/i.exec(t);
      const payMatch = /Menerima pembayaran dari\s+(.+?)(?:RRN|$)/i.exec(t);
      out.push({
        tanggal: null,
        waktu: timeMatch ? timeMatch[1].replace('.', ':') : null,
        nominal: amtMatch ? parseAmount(amtMatch[1]) : null,
        status: 'sukses',
        keterangan: payMatch ? payMatch[1].trim() : null,
        rrn: rrnMatch ? rrnMatch[1] : null,
      });
    }

    return out.filter((row) => row.rrn || row.nominal != null);
  });
}

/**
 * Ambil transaksi per tanggal dengan klik kalender dashboard.
 * @param {import('puppeteer').Page} page
 * @param {string} startYmd
 * @param {string} endYmd
 * @returns {Promise<object[]>}
 */
async function scrapeTransactionsForDateRange(page, startYmd, endYmd) {
  const all = [];
  const seen = new Set();
  const totalDays = daysBetweenYmd(startYmd, endYmd) + 1;

  for (let i = 0; i < totalDays; i++) {
    const ymd = addDaysYmd(startYmd, i);
    const selected = await selectDashboardDate(page, ymd);
    if (!selected) {
      // Tanggal mungkin sudah aktif; tetap coba scrape
    }

    await waitForDashboardTransactions(page, 12000);
    const rows = await scrapeTransactionsFromDom(page);
    for (const row of rows) {
      row.tanggal = ymd;
      const key = [row.tanggal, row.waktu, row.nominal, row.rrn].join('|');
      if (seen.has(key)) continue;
      seen.add(key);
      all.push(row);
    }
  }

  return all;
}

/**
 * Tunggu dashboard selesai load transaksi.
 * @param {import('puppeteer').Page} page
 * @param {number} timeoutMs
 */
async function waitForDashboardTransactions(page, timeoutMs = 15000) {
  await page
    .waitForFunction(
      () => {
        const t = document.body?.innerText || '';
        return (
          /RRN:/i.test(t) ||
          /Menerima pembayaran/i.test(t) ||
          /transaksi tidak ada/i.test(t) ||
          /\/home/i.test(location.pathname)
        );
      },
      { timeout: timeoutMs }
    )
    .catch(() => {});
}

module.exports = {
  scrapeTransactionsFromDom,
  scrapeTransactionsForDateRange,
  selectDashboardDate,
  waitForDashboardTransactions,
};
