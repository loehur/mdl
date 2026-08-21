require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });

const { MSSI_BASE, USER_AGENT } = require('../lib/qrms-config');

const TOKEN_URL = `${MSSI_BASE}/v1/sso/auth/realms/bca/protocol/openid-connect/token`;

async function tryLogin(form) {
  const res = await fetch(TOKEN_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      Accept: 'application/json',
      'User-Agent': USER_AGENT,
    },
    body: new URLSearchParams(form),
  });
  const text = await res.text();
  let json = null;
  try {
    json = JSON.parse(text);
  } catch (_) {}
  console.log('form keys:', Object.keys(form).join(','));
  console.log('status:', res.status, JSON.stringify(json || text.slice(0, 200)));
  return { res, json };
}

(async () => {
  const email = process.env.BCA_QRMS_EMAIL || '';
  const password = process.env.BCA_QRMS_PASSWORD || '';
  if (!email || !password) {
    console.error('Set BCA_QRMS_EMAIL and BCA_QRMS_PASSWORD in .env');
    process.exit(1);
  }

  const attempts = [
    {
      grant_type: 'password',
      client_id: 'bca-qrms',
      username: email,
      password,
    },
    {
      grant_type: 'password',
      client_id: 'bca-qrms',
      username: email,
      password,
      scope: 'openid',
    },
    {
      grant_type: 'password',
      client_id: 'QRMerchantService',
      username: email,
      password,
    },
  ];

  for (const form of attempts) {
    await tryLogin(form);
    console.log('---');
  }
})();
