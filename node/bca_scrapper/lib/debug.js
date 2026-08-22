const fs = require('fs');
const path = require('path');
const log = require('./log');

const DEBUG_ROOT = path.join(__dirname, '..', 'debug');

function isEnabled() {
  return String(process.env.BCA_DEBUG || '').toLowerCase() === 'true';
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function runStamp() {
  const d = new Date();
  return `${d.getFullYear()}${pad2(d.getMonth() + 1)}${pad2(d.getDate())}-${pad2(d.getHours())}${pad2(d.getMinutes())}${pad2(d.getSeconds())}`;
}

/**
 * @param {string} label contoh: http-balance, http-mutasi
 */
function beginRun(label) {
  if (!isEnabled()) return null;

  const id = `${runStamp()}_${label.replace(/[^\w-]+/g, '_')}`;
  const dir = path.join(DEBUG_ROOT, id);
  fs.mkdirSync(dir, { recursive: true });

  /** @type {{ id: string, dir: string, label: string, step: number, startedAt: string, steps: object[] }} */
  const run = {
    id,
    dir,
    label,
    step: 0,
    startedAt: new Date().toISOString(),
    steps: [],
  };

  step(run, 'run_started', { label });
  return run;
}

/**
 * @param {ReturnType<typeof beginRun>} run
 * @param {string} name
 * @param {Record<string, unknown>} [meta]
 */
function step(run, name, meta = {}) {
  if (!run) return;

  run.step += 1;
  const entry = {
    n: run.step,
    at: new Date().toISOString(),
    name,
    ...meta,
  };
  run.steps.push(entry);

  const parts = [`[bca_debug][${run.id}] #${run.step} ${name}`];
  if (meta.status != null) parts.push(`status=${meta.status}`);
  if (meta.url) parts.push(`url=${meta.url}`);
  if (meta.bytes != null) parts.push(`bytes=${meta.bytes}`);
  if (meta.error) parts.push(`error=${meta.error}`);
  log.log(parts.join(' '));
}

/**
 * @param {ReturnType<typeof beginRun>} run
 * @param {string} name
 * @param {string} html
 * @param {Record<string, unknown>} [meta]
 */
function saveHtml(run, name, html, meta = {}) {
  if (!run || typeof html !== 'string') return null;

  const safe = name.replace(/[^\w.-]+/g, '_');
  const file = path.join(run.dir, `${pad2(run.step + 1)}_${safe}.html`);
  fs.writeFileSync(file, html, 'utf8');
  step(run, `save_html:${safe}`, { file, bytes: html.length, ...meta });
  return file;
}

/**
 * @param {ReturnType<typeof beginRun>} run
 * @param {string} name
 * @param {unknown} data
 */
function saveJson(run, name, data) {
  if (!run) return null;
  const safe = name.replace(/[^\w.-]+/g, '_');
  const file = path.join(run.dir, `${safe}.json`);
  fs.writeFileSync(file, JSON.stringify(data, null, 2), 'utf8');
  step(run, `save_json:${safe}`, { file });
  return file;
}

/**
 * @param {ReturnType<typeof beginRun>} run
 * @param {Record<string, unknown>} summary
 */
function finishRun(run, summary = {}) {
  if (!run) return;

  const report = {
    id: run.id,
    label: run.label,
    startedAt: run.startedAt,
    finishedAt: new Date().toISOString(),
    summary,
    steps: run.steps,
  };

  const file = path.join(run.dir, 'report.json');
  fs.writeFileSync(file, JSON.stringify(report, null, 2), 'utf8');
  log.log(`[bca_debug][${run.id}] report → ${file}`);
}

module.exports = {
  isEnabled,
  beginRun,
  step,
  saveHtml,
  saveJson,
  finishRun,
  DEBUG_ROOT,
};
