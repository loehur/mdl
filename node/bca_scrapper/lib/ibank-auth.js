const crypto = require('crypto');
const { stringBetween } = require('./helpers');

/**
 * Parse waktu server dari halaman login ibank.
 * Contoh: var dtSign = new Date(2026, parseInt("08")-1, 19, 11, 37, 46);
 * @param {string} html
 */
function parseDtSign(html) {
  const m = html.match(
    /var dtSign = new Date\((\d+),\s*parseInt\("(\d+)"\)-1,\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)\)/
  );
  if (!m) return new Date();
  return new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6]);
}

/**
 * @param {string} html
 */
function parsePublicKey(html) {
  const m = html.match(/const publicKeyString = "([^"]+)"/);
  return m ? m[1] : '';
}

/** @param {Date} d */
function formatSignDate(d) {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const hour = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  const sec = String(d.getSeconds()).padStart(2, '0');
  return `${year}${month}${day}${hour}${min}${sec}`;
}

/** @param {string} publicKeyBase64 */
function toPublicKeyPem(publicKeyBase64) {
  const body = publicKeyBase64.replace(/\s+/g, '');
  const lines = body.match(/.{1,64}/g) || [body];
  return `-----BEGIN PUBLIC KEY-----\n${lines.join('\n')}\n-----END PUBLIC KEY-----`;
}

/**
 * Enkripsi PIN seperti signAndEncrypt() di ibank.klikbca.com.
 * @param {string} password PIN 6 digit
 * @param {string} publicKeyBase64
 * @param {Date} signDate waktu server (+ elapsed sejak load halaman)
 */
function encryptPassword(password, publicKeyBase64, signDate) {
  if (!publicKeyBase64) throw new Error('public_key_missing');

  const payload = `${password}${formatSignDate(signDate)}`;
  const encrypted = crypto.publicEncrypt(
    {
      key: toPublicKeyPem(publicKeyBase64),
      padding: crypto.constants.RSA_PKCS1_PADDING,
    },
    Buffer.from(payload, 'utf8')
  );

  return encrypted.toString('base64');
}

/**
 * Siapkan payload login dari HTML halaman awal ibank.
 * @param {string} loginHtml
 * @param {string} username
 * @param {string} password
 * @param {number} pageLoadedAt epoch ms saat HTML di-fetch
 */
function buildLoginPayload(loginHtml, username, password, pageLoadedAt) {
  const publicKey = parsePublicKey(loginHtml);
  const dtSign = parseDtSign(loginHtml);
  const elapsed = Math.max(0, Date.now() - pageLoadedAt);
  const signDate = new Date(dtSign.getTime() + elapsed);
  const encryptedPassword = encryptPassword(password, publicKey, signDate);

  return {
    'value(actions)': 'login',
    'value(user_id)': username,
    'value(pswd)': encryptedPassword,
    'value(Submit)': 'LOGIN',
  };
}

/**
 * @param {string} html
 */
function isLoginSuccess(html) {
  return (
    html.includes('value(actions)=logout') ||
    html.includes("value(actions)' value='logout") ||
    html.includes('MENU UTAMA')
  );
}

/**
 * @param {string} html
 */
function parseLoginError(html) {
  let err =
    stringBetween(html, "var err='", "';") ||
    stringBetween(html, 'var err="', '";') ||
    stringBetween(html, "alert('", "');") ||
    stringBetween(html, 'alert("', '");');
  if (err) return err.trim();
  if (/User ID\/Password Anda salah/i.test(html)) {
    return 'User ID/Password salah';
  }
  return '';
}

module.exports = {
  parseDtSign,
  parsePublicKey,
  formatSignDate,
  encryptPassword,
  buildLoginPayload,
  isLoginSuccess,
  parseLoginError,
};
