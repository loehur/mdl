const fs = require('fs');
const s = fs.readFileSync(require('path').join(__dirname, '..', 'debug', '_main.js'), 'utf8');
const re = /['"]([A-Za-z0-9+/=_-]{20,80})['"]/g;
const set = new Set();
let m;
while ((m = re.exec(s))) set.add(m[1]);
console.log([...set].sort().join('\n'));
