/** Simulasi: login → cache → access expired → refresh HTTP */
require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });
const qrmsSession = require('../lib/qrms-session');
const { buildEncryptedLoginPayload } = require('../lib/qrms-auth');
const { fetchToken } = require('../lib/qrms-http');

(async () => {
  const email = process.env.BCA_QRMS_EMAIL || '';
  const password = process.env.BCA_QRMS_PASSWORD || '';
  if (!email || !password) {
    console.error('missing creds');
    process.exit(1);
  }

  qrmsSession.invalidate(email);

  const loginPayload = await buildEncryptedLoginPayload(email, password, {
    headless: true,
    timeoutMs: 60000,
  });
  const loginForm = loginPayload.loginForm || {};
  const tokenJson = await fetchToken(loginForm, 30000);

  qrmsSession.save(email, {
    accessToken: tokenJson.access_token,
    refreshToken: tokenJson.refresh_token,
    hashKey: loginForm.hash_key,
    xoid: loginForm.xoid,
    appVersion: loginPayload.appVersion,
    expiresIn: tokenJson.expires_in,
    refreshExpiresIn: tokenJson.refresh_expires_in,
    outlets: [],
  });

  process.env.QRMS_SESSION_BUFFER_SEC = '0';
  qrmsSession.save(email, { accessToken: tokenJson.access_token, expiresIn: 1 });
  await new Promise((r) => setTimeout(r, 1500));

  const stale = qrmsSession.getForRefresh(email);
  if (!stale) {
    console.error('getForRefresh returned null');
    process.exit(1);
  }

  const refreshed = await fetchToken(
    {
      grant_type: 'refresh_token',
      refresh_token: stale.refreshToken,
      hash_key: stale.hashKey,
      xoid: stale.xoid,
    },
    30000
  );

  console.log('integration OK refresh expires_in=', refreshed.expires_in);
})().catch((e) => {
  console.error('integration FAIL', e.message);
  process.exit(1);
});
