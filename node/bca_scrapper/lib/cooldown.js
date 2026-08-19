/**
 * Cooldown antar scrape ke KlikBCA (cegah spam).
 */

const DEFAULT_MS = 9 * 60 * 1000;

function parseMs(envKey, fallbackMs = DEFAULT_MS) {
  const raw = process.env[envKey];
  if (raw == null || String(raw).trim() === '') {
    return fallbackMs;
  }
  const n = Number(raw);
  if (!Number.isFinite(n) || n < 0) {
    return fallbackMs;
  }
  return Math.floor(n);
}

const limits = {
  balance: parseMs('BALANCE_COOLDOWN_MS'),
  mutasi: parseMs('MUTASI_COOLDOWN_MS'),
};

/** @type {Record<'balance'|'mutasi', number|null>} */
const lastRunAt = {
  balance: null,
  mutasi: null,
};

/**
 * @param {'balance'|'mutasi'} kind
 * @returns {{ allowed: boolean, retry_after_ms?: number, retry_after_sec?: number, cooldown_ms: number }}
 */
function check(kind) {
  const cooldownMs = limits[kind] ?? DEFAULT_MS;
  if (cooldownMs <= 0) {
    return { allowed: true, cooldown_ms: 0 };
  }

  const last = lastRunAt[kind];
  if (last == null) {
    return { allowed: true, cooldown_ms: cooldownMs };
  }

  const elapsed = Date.now() - last;
  if (elapsed >= cooldownMs) {
    return { allowed: true, cooldown_ms: cooldownMs };
  }

  const retryAfterMs = cooldownMs - elapsed;
  return {
    allowed: false,
    cooldown_ms: cooldownMs,
    retry_after_ms: retryAfterMs,
    retry_after_sec: Math.ceil(retryAfterMs / 1000),
  };
}

/** @param {'balance'|'mutasi'} kind */
function mark(kind) {
  lastRunAt[kind] = Date.now();
}

function status() {
  const now = Date.now();
  const build = (kind) => {
    const cooldownMs = limits[kind] ?? DEFAULT_MS;
    const last = lastRunAt[kind];
    if (cooldownMs <= 0 || last == null) {
      return { cooldown_ms: cooldownMs, ready: true, last_run_at: last ? new Date(last).toISOString() : null };
    }
    const elapsed = now - last;
    const ready = elapsed >= cooldownMs;
    return {
      cooldown_ms: cooldownMs,
      ready,
      last_run_at: new Date(last).toISOString(),
      retry_after_sec: ready ? 0 : Math.ceil((cooldownMs - elapsed) / 1000),
    };
  };

  return {
    balance: build('balance'),
    mutasi: build('mutasi'),
  };
}

module.exports = {
  check,
  mark,
  status,
  limits,
  DEFAULT_COOLDOWN_MS: DEFAULT_MS,
};
