/** Extract readable strings from QRMS bundle for API discovery. */
const fs = require('fs');
const path = require('path');

const src = fs.readFileSync(path.join(__dirname, '..', 'debug', '_main.js'), 'utf8');
const re = /'([^'\\]{3,120})'/g;
const set = new Set();
let m;
while ((m = re.exec(src))) {
  const s = m[1];
  if (
    /token|openid|client|grant|password|mssi|ebanksvc|transaction|transaksi|history|report|login|auth|realm|qrms|merchant|v1\//i.test(s)
  ) {
    set.add(s);
  }
}
console.log([...set].sort().join('\n'));
console.error('count', set.size);
