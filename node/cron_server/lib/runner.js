const running = new Map();

function joinUrl(base, path) {
  if (/^https?:\/\//i.test(path)) return path;
  const b = String(base || '').replace(/\/+$/, '');
  const p = String(path || '').replace(/^\/+/, '');
  return `${b}/${p}`;
}

function withSecret(url, secret) {
  if (!secret) return url;
  const sep = url.includes('?') ? '&' : '?';
  return `${url}${sep}secret=${encodeURIComponent(secret)}`;
}

/**
 * @param {{ id: string, method?: string, url: string, description?: string }} job
 * @param {{ apiBase: string, cronSecret: string }} env
 */
async function runJob(job, env) {
  if (running.get(job.id)) {
    console.warn(`[cron] SKIP ${job.id} — still running`);
    return { skipped: true };
  }

  running.set(job.id, true);
  const started = Date.now();
  const method = (job.method || 'GET').toUpperCase();
  let url = joinUrl(env.apiBase, job.url);
  url = withSecret(url, env.cronSecret);

  console.log(`[cron] START ${job.id} ${method} ${url}`);

  try {
    const res = await fetch(url, {
      method,
      headers: {
        Accept: 'text/plain, application/json',
        'X-Cron-Secret': env.cronSecret || '',
      },
    });
    const text = await res.text();
    const ms = Date.now() - started;
    const preview = text.replace(/\s+/g, ' ').slice(0, 300);

    if (!res.ok) {
      console.error(`[cron] FAIL ${job.id} status=${res.status} ${ms}ms :: ${preview}`);
      return { ok: false, status: res.status, body: text };
    }

    console.log(`[cron] OK ${job.id} status=${res.status} ${ms}ms :: ${preview}`);
    return { ok: true, status: res.status, body: text };
  } catch (err) {
    const ms = Date.now() - started;
    console.error(`[cron] ERROR ${job.id} ${ms}ms ::`, err.message || err);
    return { ok: false, error: String(err.message || err) };
  } finally {
    running.set(job.id, false);
  }
}

module.exports = { runJob, joinUrl };
