/** End-to-end: login → simpan cache → HTTP refresh (+ browser fallback jika perlu). */
require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });
const qrmsSession = require('../lib/qrms-session');
const { getQrisTransactions } = require('../lib/qrms-scraper');

(async () => {
  const email = process.env.BCA_QRMS_EMAIL || '';
  const password = process.env.BCA_QRMS_PASSWORD || '';
  if (!email || !password) {
    console.error('missing creds');
    process.exit(1);
  }

  qrmsSession.invalidate(email);

  console.log('1) puppeteer login + warm crypto...');
  const first = await getQrisTransactions({
    email,
    password,
    startDate: new Date().toISOString().slice(0, 10),
    endDate: new Date().toISOString().slice(0, 10),
    httpTimeoutMs: 60000,
    puppeteerHeadless: true,
  });
  console.log('   auth=', first.data.auth_method, 'browser_state=', qrmsSession.status(email).browser_state);

  process.env.QRMS_SESSION_BUFFER_SEC = '0';
  qrmsSession.save(email, {
    accessToken: qrmsSession.getValid(email)?.accessToken,
    expiresIn: 1,
  });
  await new Promise((r) => setTimeout(r, 1500));

  console.log('2) refresh path...');
  const second = await getQrisTransactions({
    email,
    password,
    startDate: new Date().toISOString().slice(0, 10),
    endDate: new Date().toISOString().slice(0, 10),
    httpTimeoutMs: 60000,
    puppeteerHeadless: true,
  });
  console.log('   auth=', second.data.auth_method);
  if (second.data.auth_method !== 'refresh' && second.data.auth_method !== 'cache') {
    console.error('expected refresh/cache, got', second.data.auth_method);
    process.exit(1);
  }
  console.log('OK');
})().catch((e) => {
  console.error('FAIL', e.message);
  process.exit(1);
});
