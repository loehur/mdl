/**
 * Probe QRMS API paths with ONE login. Usage: node scripts/probe-qrms-api.js
 */
require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });

const fs = require('fs');
const path = require('path');
const { MSSI_BASE, BMS_BASE, USER_AGENT } = require('../lib/qrms-config');
const { buildEncryptedLoginPayload } = require('../lib/qrms-auth');
const { fetchToken } = require('../lib/qrms-http');

const OUT = path.join(__dirname, '..', 'debug', 'api-probe.json');

const PATHS = [
  'transaction-v2/v2.0.0/list',
  'api/transaction-v2/v2.0.0/list',
  'qrms/transaction-v2/v2.0.0/list',
  'mssi/transaction-v2/v2.0.0/list',
  'outlet/v1.0.0/list',
  'api/outlet/v1.0.0/list',
  'user/v1.0.0/member/list',
  'user/v1.0.0/member',
  'session/v1.0.0/add',
  'image/v1.0.0/ads/app/QRMS/list',
];

const BASES = [MSSI_BASE, BMS_BASE, `${MSSI_BASE}/api`, `${BMS_BASE}/api`];

async function tryCall(token, method, url, body) {
  const init = {
    method,
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
      'User-Agent': USER_AGENT,
    },
  };
  if (body != null) {
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(body);
  }
  const res = await fetch(url, init);
  const text = await res.text();
  let json = null;
  try {
    json = JSON.parse(text);
  } catch (_) {
    json = text.slice(0, 200);
  }
  return { status: res.status, json };
}

(async () => {
  const email = process.env.BCA_QRMS_EMAIL;
  const password = process.env.BCA_QRMS_PASSWORD;
  console.log('Login once...');
  const form = await buildEncryptedLoginPayload(email, password, { headless: true });
  const tokenJson = await fetchToken(form, 30000);
  const token = tokenJson.access_token;
  console.log('Token OK, probing APIs (no re-login)...');

  const body = {
    startDate: '2026-08-21',
    endDate: '2026-08-21',
    page: 0,
    size: 50,
  };

  const results = [];
  for (const base of BASES) {
    for (const p of PATHS) {
      const url = `${base.replace(/\/$/, '')}/${p}`;
      for (const method of ['POST', 'GET']) {
        const r = await tryCall(token, method, url, method === 'POST' ? body : null);
        if (r.status !== 404) {
          results.push({ url, method, status: r.status, preview: r.json });
          console.log(method, r.status, url);
        }
      }
    }
  }

  fs.writeFileSync(OUT, JSON.stringify(results, null, 2));
  console.log('Saved', OUT, 'hits', results.length);
})();
