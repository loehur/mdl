const fs = require('fs');
const t = fs.readFileSync(require('path').join(__dirname, '..', 'debug', '_main.js'), 'utf8');
const re = /['"](\/[a-zA-Z0-9_\-/.?=%]+)['"]/g;
const set = new Set();
let m;
while ((m = re.exec(t))) {
  if (m[1].length > 2 && m[1].length < 120) set.add(m[1]);
}
const hits = [...set]
  .filter((u) => /api|login|trans|auth|report|history|merchant|trx|qris|settle|user|token/i.test(u))
  .sort();
console.log(hits.join('\n'));
console.error('count', hits.length);
