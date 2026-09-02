import { z } from 'zod';

const schema = z.object({
  NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
  HOST: z.string().default('127.0.0.1'),
  PORT: z.coerce.number().int().min(1).max(65535).default(3001),
  DATABASE_URL: z.string().url(),
  GOOGLE_OAUTH_CLIENT_IDS: z.string().min(1),
  MEMORY_ENCRYPTION_KEY_BASE64: z.string().min(1),
  JWT_SECRET: z.string().min(32),
  // Long-lived browser session. Keep the allowed values bounded so a leaked
  // browser token cannot become a permanent credential.
  JWT_EXPIRES_IN: z.enum(['1d', '7d', '30d', '90d']).default('30d'),
  OPENAI_API_KEY: z.string().optional(),
  OPENAI_MODEL: z.string().default('gpt-5.6-luna'),
  DEEPSEEK_API_KEY: z.string().optional(),
  DEEPSEEK_BASE_URL: z.string().url().default('https://api.deepseek.com'),
  DEEPSEEK_MODEL: z.string().default('deepseek-chat'),
  EMBEDDING_PROVIDER: z.enum(['openai']).default('openai'),
  EMBEDDING_MODEL: z.string().default('text-embedding-3-small'),
  EMBEDDING_DIMENSIONS: z.coerce.number().int().positive().default(1536),
  CORS_ORIGINS: z.string().default('http://localhost:5173')
});

export type AppConfig = z.infer<typeof schema> & { corsOrigins: string[] };

export function loadConfig(environment = process.env): AppConfig {
  const parsed = schema.safeParse(environment);
  if (!parsed.success) throw new Error(`Invalid environment: ${parsed.error.message}`);
  const encryptionKey = Buffer.from(parsed.data.MEMORY_ENCRYPTION_KEY_BASE64, 'base64');
  if (encryptionKey.length !== 32) throw new Error('MEMORY_ENCRYPTION_KEY_BASE64 must decode to exactly 32 bytes');
  return { ...parsed.data, corsOrigins: parsed.data.CORS_ORIGINS.split(',').map((value) => value.trim()).filter(Boolean) };
}
