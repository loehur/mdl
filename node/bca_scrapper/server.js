require('dotenv').config();

const express = require('express');
const cors = require('cors');
const { getBalance, getMutasi } = require('./lib/scraper');
const { getQrisTransactions } = require('./lib/qrms-scraper');
const debug = require('./lib/debug');
const { validateMutasiDateRange, validateQrisDateRange, MAX_RANGE_DAYS, MAX_LOOKBACK_DAYS, QRIS_MAX_RANGE_DAYS, QRIS_MAX_LOOKBACK_DAYS, TZ } = require('./lib/date-range');
const cooldown = require('./lib/cooldown');

const app = express();
app.use(express.json({ limit: '16kb' }));
app.use(express.urlencoded({ extended: true }));
app.use(cors());

const PORT = Number(process.env.PORT || 3021);
const HOST = process.env.HOST || '0.0.0.0';
const AUTH_TOKEN = String(process.env.BCA_SCRAPPER_TOKEN || '').trim();
const DEFAULT_USERNAME = String(process.env.BCA_USERNAME || '').trim();
const DEFAULT_PASSWORD = String(process.env.BCA_PASSWORD || '').trim();
const DEFAULT_QRMS_EMAIL = String(process.env.BCA_QRMS_EMAIL || '').trim();
const DEFAULT_QRMS_PASSWORD = String(process.env.BCA_QRMS_PASSWORD || '').trim();
const HTTP_TIMEOUT_MS = Number(process.env.HTTP_TIMEOUT_MS || 30000);
const PUPPETEER_HEADLESS = String(process.env.PUPPETEER_HEADLESS || 'true').toLowerCase() !== 'false';
const PUPPETEER_TIMEOUT_MS = Number(process.env.PUPPETEER_TIMEOUT_MS || 60000);

/** Satu request scraping aktif agar tidak bentrok session KlikBCA. */
let scrapeBusy = false;

function requireToken(req, res, next) {
  if (!AUTH_TOKEN) return next();

  const header = String(req.headers['x-bca-token'] || req.headers['authorization'] || '');
  const bearer = header.toLowerCase().startsWith('bearer ')
    ? header.slice(7).trim()
    : header.trim();
  const q = String(req.query.token || '');

  if (bearer === AUTH_TOKEN || q === AUTH_TOKEN) return next();

  console.warn('[bca_scrapper] 401 unauthorized', req.method, req.path);
  return res.status(401).json({
    ok: false,
    error: 'unauthorized',
    message:
      'Token bca_scrapper tidak cocok. Samakan BCA_SCRAPPER_TOKEN di client dengan node/bca_scrapper/.env.',
  });
}

function pickCredentials(req) {
  const body = req.body && typeof req.body === 'object' ? req.body : {};
  const q = req.query || {};
  const username = String(body.username || q.username || DEFAULT_USERNAME || '').trim();
  const password = String(body.password || q.password || DEFAULT_PASSWORD || '').trim();
  return { username, password };
}

function pickQrmsCredentials(req) {
  const body = req.body && typeof req.body === 'object' ? req.body : {};
  const q = req.query || {};
  const email = String(
    body.email || q.email || body.username || q.username || DEFAULT_QRMS_EMAIL || ''
  ).trim();
  const password = String(body.password || q.password || DEFAULT_QRMS_PASSWORD || '').trim();
  return { email, password };
}

function pickDateRange(req) {
  const body = req.body && typeof req.body === 'object' ? req.body : {};
  const q = req.query || {};
  const startDate = body.start_date || body.startDate || q.start_date || q.startDate || null;
  const endDate = body.end_date || body.endDate || q.end_date || q.endDate || null;
  return { startDate, endDate };
}

function scraperOptions() {
  return {
    httpTimeoutMs: HTTP_TIMEOUT_MS,
    puppeteerHeadless: PUPPETEER_HEADLESS,
    puppeteerTimeoutMs: PUPPETEER_TIMEOUT_MS,
  };
}

async function withScrapeLock(handler) {
  if (scrapeBusy) {
    const err = new Error('scraper_busy');
    err.code = 'scraper_busy';
    throw err;
  }
  scrapeBusy = true;
  try {
    return await handler();
  } finally {
    scrapeBusy = false;
  }
}

function rejectCooldown(res, kind, gate) {
  const labels = { balance: 'saldo', mutasi: 'mutasi', qris: 'transaksi QRIS' };
  const label = labels[kind] || kind;
  const sec = gate.retry_after_sec ?? 0;
  const min = Math.max(1, Math.ceil(sec / 60));
  return res.status(429).json({
    ok: false,
    error: 'cooldown',
    message: `Scrape ${label} BCA cooldown aktif. Coba lagi dalam ~${min} menit (${sec} detik).`,
    cooldown_ms: gate.cooldown_ms,
    retry_after_ms: gate.retry_after_ms,
    retry_after_sec: sec,
  });
}

async function handleBalance(req, res) {
  const { username, password } = pickCredentials(req);
  if (!username || !password) {
    return res.status(400).json({
      ok: false,
      error: 'credentials_required',
      message: 'username dan password wajib (body atau env BCA_USERNAME/BCA_PASSWORD)',
    });
  }

  const cd = cooldown.check('balance');
  if (!cd.allowed) {
    return rejectCooldown(res, 'balance', cd);
  }
  cooldown.mark('balance');

  try {
    const result = await withScrapeLock(() =>
      getBalance({
        username,
        password,
        ...scraperOptions(),
      })
    );

    return res.json({
      ok: true,
      method: result.method,
      http_error: result.http_error || null,
      balance: result.data,
      timestamp: new Date().toISOString(),
    });
  } catch (err) {
    const code = err && err.code ? err.code : 'scrape_failed';
    const status = code === 'scraper_busy' || code === 'cooldown' ? 429 : 502;
    return res.status(status).json({
      ok: false,
      error: code,
      message: err instanceof Error ? err.message : 'Gagal mengambil saldo BCA',
      http_error: err.http_error || null,
      puppeteer_error: err.puppeteer_error || null,
    });
  }
}

async function handleMutasi(req, res) {
  const { username, password } = pickCredentials(req);
  const { startDate, endDate } = pickDateRange(req);

  if (!username || !password) {
    return res.status(400).json({
      ok: false,
      error: 'credentials_required',
      message: 'username dan password wajib (body atau env BCA_USERNAME/BCA_PASSWORD)',
    });
  }

  let validatedDates;
  try {
    validatedDates = validateMutasiDateRange(startDate, endDate);
  } catch (err) {
    const code = err && err.code ? err.code : 'invalid_date';
    return res.status(400).json({
      ok: false,
      error: code,
      message: err instanceof Error ? err.message : 'Rentang tanggal tidak valid',
    });
  }

  const cd = cooldown.check('mutasi');
  if (!cd.allowed) {
    return rejectCooldown(res, 'mutasi', cd);
  }
  cooldown.mark('mutasi');

  try {
    const result = await withScrapeLock(() =>
      getMutasi({
        username,
        password,
        startDate: validatedDates.startDate,
        endDate: validatedDates.endDate,
        ...scraperOptions(),
      })
    );

    return res.json({
      ok: true,
      method: result.method,
      http_error: result.http_error || null,
      start_date: result.data.start,
      end_date: result.data.end,
      mutasi: result.data.mutasi,
      count: result.data.mutasi.length,
      timestamp: new Date().toISOString(),
    });
  } catch (err) {
    const code = err && err.code ? err.code : 'scrape_failed';
    const status = code === 'scraper_busy' || code === 'cooldown' ? 429 : 502;
    return res.status(status).json({
      ok: false,
      error: code,
      message: err instanceof Error ? err.message : 'Gagal mengambil mutasi BCA',
      http_error: err.http_error || null,
      puppeteer_error: err.puppeteer_error || null,
    });
  }
}

async function handleQrisTransactions(req, res) {
  const { email, password } = pickQrmsCredentials(req);
  const { startDate, endDate } = pickDateRange(req);

  if (!email || !password) {
    return res.status(400).json({
      ok: false,
      error: 'credentials_required',
      message: 'email dan password QRMS wajib (body atau env BCA_QRMS_EMAIL/BCA_QRMS_PASSWORD)',
    });
  }

  let validatedDates;
  try {
    validatedDates = validateQrisDateRange(startDate, endDate);
  } catch (err) {
    const code = err && err.code ? err.code : 'invalid_date';
    return res.status(400).json({
      ok: false,
      error: code,
      message: err instanceof Error ? err.message : 'Rentang tanggal tidak valid',
    });
  }

  const cd = cooldown.check('qris');
  if (!cd.allowed) {
    return rejectCooldown(res, 'qris', cd);
  }
  cooldown.mark('qris');

  try {
    const result = await withScrapeLock(() =>
      getQrisTransactions({
        email,
        password,
        startDate: validatedDates.startDate,
        endDate: validatedDates.endDate,
        httpTimeoutMs: HTTP_TIMEOUT_MS,
        puppeteerHeadless: PUPPETEER_HEADLESS,
      })
    );

    return res.json({
      ok: true,
      method: result.method,
      start_date: result.data.start,
      end_date: result.data.end,
      transactions: result.data.transactions,
      count: result.data.transactions.length,
      outlets: result.data.outlets || [],
      timestamp: new Date().toISOString(),
    });
  } catch (err) {
    const code = err && err.code ? err.code : 'scrape_failed';
    const status = code === 'scraper_busy' || code === 'cooldown' ? 429 : 502;
    const message =
      err instanceof Error && err.message === 'login_failed'
        ? 'Login QRMS gagal. Periksa BCA_QRMS_EMAIL dan BCA_QRMS_PASSWORD.'
        : err instanceof Error
          ? err.message
          : 'Gagal mengambil transaksi QRIS';
    return res.status(status).json({
      ok: false,
      error: err instanceof Error && err.message === 'login_failed' ? 'login_failed' : code,
      message,
      puppeteer_error: err instanceof Error ? err.message : null,
    });
  }
}

app.get('/health', (_req, res) => {
  res.json({
    ok: true,
    status: 'running',
    service: 'bca_scrapper',
    auth_required: Boolean(AUTH_TOKEN),
    busy: scrapeBusy,
    cooldown: cooldown.status(),
    strategy: 'http_first_then_puppeteer',
    timestamp: new Date().toISOString(),
  });
});

app.get('/favicon.ico', (_req, res) => res.status(204).end());

app.post('/balance', requireToken, handleBalance);
app.get('/balance', requireToken, handleBalance);

app.post('/mutasi', requireToken, handleMutasi);
app.get('/mutasi', requireToken, handleMutasi);

app.post('/qris/transactions', requireToken, handleQrisTransactions);
app.get('/qris/transactions', requireToken, handleQrisTransactions);

app.listen(PORT, HOST, () => {
  console.log('========================================');
  console.log('  BCA Scrapper (KlikBCA mutasi + QRMS transaksi)');
  console.log('========================================');
  console.log(`  HTTP: http://${HOST}:${PORT}`);
  console.log(`  Auth: ${AUTH_TOKEN ? 'X-Bca-Token required' : 'open (set BCA_SCRAPPER_TOKEN)'}`);
  console.log('  POST /balance  { username, password }');
  console.log('  POST /mutasi   { username, password, start_date?, end_date? }');
  console.log('  POST /qris/transactions { start_date?, end_date? } — max kemarin+hari ini, lookback 1 hari');
  console.log('  GET  /health');
  console.log(`  Debug: ${debug.isEnabled() ? 'ON → saves to debug/' : 'off (set BCA_DEBUG=true)'}`);
  console.log(`  Mutasi limits: end<=today (${TZ}), max ${MAX_RANGE_DAYS} days range, max ${MAX_LOOKBACK_DAYS} days lookback`);
  console.log(`  QRIS limits: max ${QRIS_MAX_RANGE_DAYS} days range, max ${QRIS_MAX_LOOKBACK_DAYS} days lookback (${TZ})`);
  console.log(
    `  Cooldown: balance ${Math.round(cooldown.limits.balance / 60000)}m, mutasi ${Math.round(cooldown.limits.mutasi / 60000)}m, qris ${Math.round(cooldown.limits.qris / 60000)}m`
  );
  console.log('  Strategy: ibank HTTP → Puppeteer fallback; QRMS HTTP (encrypt + MSSI API)');
  console.log('========================================');
});
