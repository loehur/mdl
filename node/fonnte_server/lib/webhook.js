const WEBHOOK_URL = String(process.env.WEBHOOK_URL || '').trim();
const RETRY = Math.max(1, Number(process.env.WEBHOOK_RETRY || 3));
const TIMEOUT_MS = Number(process.env.WEBHOOK_TIMEOUT_MS || 15000);

async function postWebhook(payload) {
  if (!WEBHOOK_URL) {
    console.warn('[webhook] WEBHOOK_URL not set — skip');
    return { ok: false, skipped: true };
  }

  let lastErr = null;
  for (let attempt = 1; attempt <= RETRY; attempt++) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
    try {
      const res = await fetch(WEBHOOK_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json; charset=utf-8' },
        body: JSON.stringify(payload),
        signal: controller.signal,
      });
      clearTimeout(timer);
      const text = await res.text();
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}: ${text.slice(0, 200)}`);
      }
      return { ok: true, status: res.status, body: text.slice(0, 500) };
    } catch (err) {
      clearTimeout(timer);
      lastErr = err;
      console.error(`[webhook] attempt ${attempt}/${RETRY} failed:`, err.message || err);
      if (attempt < RETRY) {
        await new Promise((r) => setTimeout(r, attempt * 1000));
      }
    }
  }
  return { ok: false, error: String(lastErr?.message || lastErr) };
}

module.exports = { postWebhook };
