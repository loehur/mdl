const path = require('path');
const express = require('express');
const cron = require('node-cron');
const dotenv = require('dotenv');
const { runJob } = require('./lib/runner');
const jobs = require('./jobs.config');

const envPath = path.resolve(__dirname, '.env');
const loaded = dotenv.config({ path: envPath });
if (loaded.error) {
  console.warn('[cron_server] .env not found, using process env / defaults');
}

const PORT = Number(process.env.PORT || 3011);
const API_BASE = process.env.API_BASE || 'http://localhost/mdl/api';
const CRON_SECRET = process.env.CRON_SECRET || '';
const TZ = process.env.TZ || 'Asia/Jakarta';

const app = express();
const lastRuns = {};

app.get('/', (_req, res) => {
  res.json({
    status: 'ok',
    service: 'cron_server',
    tz: TZ,
    jobs: jobs.map((j) => ({
      id: j.id,
      schedule: j.schedule,
      enabled: j.enabled !== false,
      description: j.description || '',
      lastRun: lastRuns[j.id] || null,
    })),
  });
});

app.post('/run/:id', async (req, res) => {
  const job = jobs.find((j) => j.id === req.params.id);
  if (!job) {
    return res.status(404).json({ status: false, message: 'Job not found' });
  }

  const result = await runJob(job, { apiBase: API_BASE, cronSecret: CRON_SECRET });
  lastRuns[job.id] = {
    at: new Date().toISOString(),
    ok: !!result.ok,
    status: result.status || null,
  };
  res.json({ status: true, result });
});

function registerJobs() {
  for (const job of jobs) {
    if (job.enabled === false) {
      console.log(`[cron_server] disabled: ${job.id}`);
      continue;
    }
    if (!cron.validate(job.schedule)) {
      console.error(`[cron_server] invalid schedule for ${job.id}: ${job.schedule}`);
      continue;
    }

    cron.schedule(
      job.schedule,
      async () => {
        const result = await runJob(job, { apiBase: API_BASE, cronSecret: CRON_SECRET });
        lastRuns[job.id] = {
          at: new Date().toISOString(),
          ok: !!result.ok,
          status: result.status || null,
        };
      },
      { timezone: TZ }
    );

    console.log(`[cron_server] scheduled ${job.id} :: ${job.schedule} (${TZ})`);
  }
}

registerJobs();

app.listen(PORT, () => {
  console.log(`[cron_server] listening on :${PORT}`);
  console.log(`[cron_server] API_BASE=${API_BASE}`);
  if (!CRON_SECRET) {
    console.warn('[cron_server] WARNING: CRON_SECRET is empty');
  }
});
