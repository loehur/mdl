// Jalankan job secara serial agar scraper berat tidak membebani server.
// Job yang sama dideduplikasi: bila sudah antre/berjalan, pemanggil berikutnya
// menunggu hasil eksekusi yang sama tanpa membuat permintaan baru.
let queue = Promise.resolve();
const queuedJobs = new Map();

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
async function executeJob(job, env) {
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
  }
}

function runJob(job, env) {
  if (queuedJobs.has(job.id)) {
    console.log(`[cron] JOIN ${job.id} — run already queued or in progress`);
    return queuedJobs.get(job.id);
  }

  const task = queue.then(() => executeJob(job, env));
  queuedJobs.set(job.id, task);
  queue = task.catch(() => undefined);
  task.finally(() => queuedJobs.delete(job.id));
  return task;
}

module.exports = { runJob, joinUrl };
