const fs = require('fs');
const path = require('path');

const COUNTER_FILE = path.join(__dirname, '..', 'auth', 'inbox_counter.json');

function ensureDir() {
  const dir = path.dirname(COUNTER_FILE);
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

/**
 * Monotonic inboxid (kompatibel field Fonnte webhook).
 */
function nextInboxId() {
  ensureDir();
  let current = 210700000;
  try {
    if (fs.existsSync(COUNTER_FILE)) {
      const raw = JSON.parse(fs.readFileSync(COUNTER_FILE, 'utf8'));
      if (Number.isFinite(raw.last)) {
        current = raw.last;
      }
    }
  } catch (_) {
    /* reset */
  }
  const next = current + 1;
  fs.writeFileSync(COUNTER_FILE, JSON.stringify({ last: next, updated_at: new Date().toISOString() }), 'utf8');
  return next;
}

module.exports = { nextInboxId };
