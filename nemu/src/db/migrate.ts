import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadConfig } from '../config/env.js';
import { createPool } from './pool.js';

const migrationDirectory = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../migrations');

async function main() {
  const config = loadConfig();
  const pool = createPool(config.DATABASE_URL);
  const client = await pool.connect();
  try {
    await client.query('CREATE TABLE IF NOT EXISTS schema_migrations (name text PRIMARY KEY, applied_at timestamptz NOT NULL DEFAULT now())');
    const applied = new Set((await client.query<{ name: string }>('SELECT name FROM schema_migrations')).rows.map((row) => row.name));
    for (const name of (await readdir(migrationDirectory)).filter((file) => file.endsWith('.sql')).sort()) {
      if (applied.has(name)) continue;
      await client.query('BEGIN');
      try {
        await client.query(await readFile(path.join(migrationDirectory, name), 'utf8'));
        await client.query('INSERT INTO schema_migrations (name) VALUES ($1)', [name]);
        await client.query('COMMIT');
        console.log(`Applied ${name}`);
      } catch (error) {
        await client.query('ROLLBACK');
        throw error;
      }
    }
  } finally { client.release(); await pool.end(); }
}

main().catch((error: unknown) => { console.error(error); process.exitCode = 1; });
