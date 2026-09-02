import { describe, expect, it } from 'vitest';
import { buildApp } from '../src/app.js';
import { loadConfig } from '../src/config/env.js';

describe('application foundation', () => {
  it('rejects configuration without the trusted Google audience', () => {
    expect(() => loadConfig({ DATABASE_URL: 'postgres://localhost/nemu' })).toThrow('Invalid environment');
  });

  it('serves health without accessing protected resources', async () => {
    const app = buildApp(loadConfig({
      DATABASE_URL: 'postgres://user:password@localhost:5432/nemu',
      GOOGLE_OAUTH_CLIENT_IDS: 'test-client.apps.googleusercontent.com',
      MEMORY_ENCRYPTION_KEY_BASE64: Buffer.alloc(32, 7).toString('base64'),
      JWT_SECRET: 'a-test-secret-that-is-longer-than-thirty-two-characters'
    }));
    const response = await app.inject({ method: 'GET', url: '/health' });
    expect(response.statusCode).toBe(200);
    expect(response.json()).toEqual({ status: 'ok' });
    await app.close();
  });
});
