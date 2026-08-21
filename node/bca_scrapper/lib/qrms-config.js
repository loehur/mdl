/** QR Merchant Service (qr.klikbca.com). */
const QRMS_BASE = 'https://qr.klikbca.com';
const MSSI_BASE = 'https://mssi.ebanksvc.bca.co.id';
const BMS_BASE = 'https://bms.ebanksvc.bca.co.id';

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

const URLS = {
  login: `${QRMS_BASE}/login`,
  home: `${QRMS_BASE}/`,
  referer: `${QRMS_BASE}/`,
};

const API = {
  token: `${MSSI_BASE}/v1/sso/auth/realms/bca/protocol/openid-connect/token`,
  outletList: `${BMS_BASE}/outlet/v1.0.0/list`,
  transactionList: `${BMS_BASE}/transaction-v2/v2.0.0/list`,
};

module.exports = {
  QRMS_BASE,
  MSSI_BASE,
  BMS_BASE,
  USER_AGENT,
  URLS,
  API,
};
