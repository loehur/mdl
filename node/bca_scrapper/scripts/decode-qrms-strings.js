/**
 * Decode obfuscated string table from QRMS main.js (for dev discovery only).
 */
const fs = require('fs');
const vm = require('vm');
const path = require('path');

const src = fs.readFileSync(path.join(__dirname, '..', 'debug', '_main.js'), 'utf8');
const start = src.indexOf('function _0x15b5(){');
const end = src.indexOf('};', start) + 2;
if (start < 0) throw new Error('_0x15b5 not found');

const snippet = `${src.slice(start, end)}
module.exports = { decode: _0x5005, table: _0x15b5 };`;

const sandbox = { module: { exports: {} } };
vm.createContext(sandbox);
vm.runInContext(snippet, sandbox);
const { decode, table } = sandbox.module.exports;

const arr = table();
const hits = arr.filter(
  (s) =>
    typeof s === 'string' &&
    (/ebanksvc|login|trans|auth|token|report|history|merchant|trx|qris|date|settle|password|email|user/i.test(s) ||
      /^\/[a-z]/i.test(s))
);
console.log(hits.sort().join('\n'));
console.error('hits', hits.length, 'total', arr.length);

// Try decoding a range of indices that might be API paths
const extra = [];
for (let i = 0; i < 5000; i++) {
  try {
    const s = decode(i);
    if (typeof s === 'string' && /ebanksvc|\/api|login|trans|auth|history|report|merchant/i.test(s)) {
      extra.push(`${i}: ${s}`);
    }
  } catch (_) {}
}
console.log('\n--- decoded sample ---\n' + extra.slice(0, 100).join('\n'));
