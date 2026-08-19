/** Base URL KlikBCA Individual (desktop). */
const IBANK_BASE = 'https://ibank.klikbca.com';

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

const URLS = {
  home: `${IBANK_BASE}/`,
  auth: `${IBANK_BASE}/authentication.do`,
  authWelcome: `${IBANK_BASE}/authentication.do?value(actions)=welcome`,
  accountMenu: `${IBANK_BASE}/nav_bar_indo/account_information_menu.htm`,
  accountStmt: `${IBANK_BASE}/accountstmt.do`,
  accountStmtView: `${IBANK_BASE}/accountstmt.do?value(actions)=acctstmtview`,
  balance: `${IBANK_BASE}/balanceinquiry.do`,
  top: `${IBANK_BASE}/top.htm`,
};

module.exports = {
  IBANK_BASE,
  USER_AGENT,
  URLS,
};
