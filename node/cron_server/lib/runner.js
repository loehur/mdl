// Semua job dieksekusi satu per satu lewat antrean FIFO agar beban server
// terkontrol. State tetap disimpan per job untuk coalescing:
// - trigger saat job menunggu cukup bergabung ke antrean yang sudah ada;
// - trigger saat job aktif meminta satu rerun di belakang antrean.
const jobStates = new Map();
const jobQueue = [];
let workerBusy = false;

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

async function drainQueue() {
  if (workerBusy) return;
  workerBusy = true;

  try {
    while (jobQueue.length > 0) {
      const state = jobQueue.shift();
      state.status = 'running';
      let result;
      try {
        result = await executeJob(state.job, state.env);
      } catch (err) {
        // executeJob biasanya sudah menangani error request. Fallback ini
        // menjaga worker tetap bergerak jika terjadi error tak terduga.
        console.error(`[cron] ERROR ${state.job.id} — unexpected runner failure`, err);
        result = { ok: false, error: String(err.message || err) };
      }

      if (state.rerunRequested) {
        state.rerunRequested = false;
        state.status = 'queued';
        jobQueue.push(state);
        console.log(`[cron] RERUN ${state.job.id} — requeued after coalesced trigger`);
        continue;
      }

      jobStates.delete(state.job.id);
      state.resolve(result);
    }
  } finally {
    workerBusy = false;

    // Trigger yang masuk di sela worker selesai dan flag dilepas tetap diproses.
    if (jobQueue.length > 0) void drainQueue();
  }
}

function runJob(job, env) {
  const existing = jobStates.get(job.id);
  if (existing) {
    if (existing.status === 'running') {
      existing.rerunRequested = true;
      console.log(`[cron] COALESCE ${job.id} — rerun requested after active run`);
    } else {
      console.log(`[cron] COALESCE ${job.id} — already waiting in queue`);
    }
    return existing.task;
  }

  let resolve;
  const task = new Promise((done) => {
    resolve = done;
  });
  const state = { job, env, status: 'queued', rerunRequested: false, task, resolve };

  jobStates.set(job.id, state);
  jobQueue.push(state);
  console.log(`[cron] QUEUE ${job.id} — position ${jobQueue.length}`);
  void drainQueue();
  return task;
}

module.exports = { runJob, joinUrl };
