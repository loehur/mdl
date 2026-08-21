const { fetchQrisTransactionsHttp } = require('./qrms-http');

/**
 * Ambil transaksi QRIS merchant via HTTP (encrypt login payload singkat + API MSSI).
 * @param {{
 *   email: string,
 *   password: string,
 *   startDate: string,
 *   endDate: string,
 *   httpTimeoutMs?: number,
 *   puppeteerHeadless?: boolean,
 * }} opts
 */
async function getQrisTransactions(opts) {
  const data = await fetchQrisTransactionsHttp({
    email: opts.email,
    password: opts.password,
    startDate: opts.startDate,
    endDate: opts.endDate,
    timeoutMs: opts.httpTimeoutMs,
    headless: opts.puppeteerHeadless,
  });
  return { ok: true, method: 'http', data };
}

module.exports = {
  getQrisTransactions,
};
