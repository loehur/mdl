const fs = require('fs');
const t = fs.readFileSync(require('path').join(__dirname, '..', 'debug', '_main.js'), 'utf8');
const re = /['"](https?:[^'"]+)['"]/g;
const set = new Set();
let m;
while ((m = re.exec(t))) set.add(m[1]);
console.log([...set].sort().join('\n'));
console.error('count', set.size);
