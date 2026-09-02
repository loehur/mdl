import { Pool } from 'pg';

export function createPool(connectionString: string): Pool {
  return new Pool({ connectionString, max: 10, statement_timeout: 10_000 });
}
