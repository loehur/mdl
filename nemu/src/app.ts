import cors from '@fastify/cors';
import helmet from '@fastify/helmet';
import jwt from '@fastify/jwt';
import rateLimit from '@fastify/rate-limit';
import Fastify from 'fastify';
import { z } from 'zod';
import type { AppConfig } from './config/env.js';
import { createPool } from './db/pool.js';
import { AuthService } from './modules/auth/auth-service.js';
import { GoogleTokenVerifier } from './modules/auth/google-verifier.js';
import { OpenAiEmbeddingProvider } from './modules/ai/embedding-provider.js';
import { createChatProvider } from './modules/ai/chat-provider-factory.js';
import { MemoryRepository } from './modules/memory/memory-repository.js';
import { MemoryService } from './modules/memory/memory-service.js';
import { MemoryEncryption } from './modules/security/encryption.js';

type SessionToken = { sub: string; tenantId: string; role: 'owner' | 'admin' | 'member' };

export function buildApp(config: AppConfig) {
  const app = Fastify({ logger: { level: config.NODE_ENV === 'development' ? 'debug' : 'info', redact: ['req.headers.authorization', 'req.body.idToken'] }, bodyLimit: 64 * 1024 });
  const db = createPool(config.DATABASE_URL);
  const auth = new AuthService(db);
  const verifier = new GoogleTokenVerifier(config.GOOGLE_OAUTH_CLIENT_IDS.split(',').map((value) => value.trim()));
  const memories = new MemoryService(new MemoryRepository(db), new MemoryEncryption(config.MEMORY_ENCRYPTION_KEY_BASE64), new OpenAiEmbeddingProvider(config.OPENAI_API_KEY, config.EMBEDDING_MODEL, config.EMBEDDING_DIMENSIONS), createChatProvider(config, () => app.log.warn('Primary chat provider unavailable; using fallback')), config.NODE_ENV === 'development' ? (details) => app.log.debug(details, 'ASK retrieval diagnostics') : undefined);
  app.addHook('onClose', async () => db.end());
  app.register(helmet);
  app.register(cors, { origin: config.corsOrigins, credentials: true });
  app.register(rateLimit, { max: 100, timeWindow: '1 minute' });
  app.register(jwt, { secret: config.JWT_SECRET });
  app.setErrorHandler((error, request, reply) => { const handled = error as { code?: string; statusCode?: number }; request.log.warn({ code: handled.code }, 'Request failed'); return reply.code(handled.statusCode && handled.statusCode < 500 ? handled.statusCode : 500).send({ error: 'Request failed' }); });
  app.get('/health', async () => ({ status: 'ok' }));
  app.post('/v1/auth/session', { config: { rateLimit: { max: 15, timeWindow: '1 minute' } } }, async (request, reply) => {
    const parsed = z.object({ idToken: z.string().min(1).max(12_000) }).safeParse(request.body);
    if (!parsed.success) return reply.code(400).send({ error: 'Invalid request body' });
    try {
      const identity = await verifier.verify(parsed.data.idToken);
      const session = await auth.resolveOrProvision(identity);
      const accessToken = app.jwt.sign(
        { sub: session.userId, tenantId: session.tenantId, role: session.role } satisfies SessionToken,
        { expiresIn: config.JWT_EXPIRES_IN }
      );
      return reply.code(200).send({ data: { ...session, accessToken } });
    } catch { return reply.code(401).send({ error: 'Invalid Google identity token' }); }
  });
  app.get('/v1/me', async (request, reply) => {
    try { const token = await request.jwtVerify<SessionToken>(); return reply.send({ data: await auth.getProfile(token.sub) }); }
    catch { return reply.code(401).send({ error: 'Authentication required' }); }
  });
  app.get('/v1/memories', async (request, reply) => {
    try {
      const token = await request.jwtVerify<SessionToken>();
      const query = z.object({ limit: z.coerce.number().int().min(1).max(100).default(30), query: z.string().trim().min(2).max(200).optional() }).safeParse(request.query);
      if (!query.success) return reply.code(400).send({ error: 'Invalid query' });
      return reply.send({ data: query.data.query ? await memories.search(token.tenantId, query.data.query, query.data.limit) : await memories.list(token.tenantId, query.data.limit) });
    } catch { return reply.code(401).send({ error: 'Authentication required' }); }
  });
  app.get('/v1/memories/count', async (request, reply) => {
    try { const token = await request.jwtVerify<SessionToken>(); return reply.send({ data: { total: await memories.count(token.tenantId) } }); }
    catch { return reply.code(401).send({ error: 'Authentication required' }); }
  });
  app.get('/v1/plan', async (request, reply) => { try { const token = await request.jwtVerify<SessionToken>(); return reply.send({ data: await memories.plan(token.tenantId) }); } catch { return reply.code(401).send({ error: 'Authentication required' }); } });
  app.get('/v1/memories/trash', async (request, reply) => {
    try {
      const token = await request.jwtVerify<SessionToken>();
      const query = z.object({ limit: z.coerce.number().int().min(1).max(100).default(30) }).safeParse(request.query);
      if (!query.success) return reply.code(400).send({ error: 'Invalid query' });
      return reply.send({ data: await memories.trashList(token.tenantId, query.data.limit) });
    } catch { return reply.code(401).send({ error: 'Authentication required' }); }
  });
  app.delete('/v1/memories/trash', async (request, reply) => {
    try { const token = await request.jwtVerify<SessionToken>(); return reply.send({ data: { deleted: await memories.emptyTrash(token.tenantId) } }); }
    catch { return reply.code(401).send({ error: 'Authentication required' }); }
  });
  app.delete('/v1/memories/:id', async (request, reply) => {
    try {
      const token = await request.jwtVerify<SessionToken>();
      const params = z.object({ id: z.string().uuid() }).safeParse(request.params);
      if (!params.success) return reply.code(400).send({ error: 'Invalid memory id' });
      const removed = await memories.trash({ tenantId: token.tenantId, userId: token.sub, memoryId: params.data.id });
      if (!removed) return reply.code(404).send({ error: 'Memory not found' });
      return reply.send({ data: { id: params.data.id, deleted: true } });
    } catch { return reply.code(401).send({ error: 'Authentication required' }); }
  });
  app.put('/v1/memories/:id', async (request, reply) => {
    try {
      const token = await request.jwtVerify<SessionToken>();
      const params = z.object({ id: z.string().uuid() }).safeParse(request.params);
      const body = z.object({ content: z.string().trim().min(1).max(16_000) }).safeParse(request.body);
      if (!params.success || !body.success) return reply.code(400).send({ error: 'Invalid memory id or content' });
      const updated = await memories.updateContent({ tenantId: token.tenantId, userId: token.sub, memoryId: params.data.id, content: body.data.content });
      if (!updated) return reply.code(404).send({ error: 'Memory not found' });
      return reply.send({ data: updated });
    } catch { return reply.code(401).send({ error: 'Authentication required' }); }
  });
  app.post('/v1/memories/:id/restore', async (request, reply) => {
    try {
      const token = await request.jwtVerify<SessionToken>();
      const params = z.object({ id: z.string().uuid() }).safeParse(request.params);
      if (!params.success) return reply.code(400).send({ error: 'Invalid memory id' });
      const restored = await memories.restore({ tenantId: token.tenantId, userId: token.sub, memoryId: params.data.id });
      if (!restored) return reply.code(404).send({ error: 'Memory not found in trash' });
      return reply.send({ data: { id: params.data.id, restored: true } });
    } catch { return reply.code(401).send({ error: 'Authentication required' }); }
  });
  app.post('/v1/memories', { config: { rateLimit: { max: 30, timeWindow: '1 minute' } } }, async (request, reply) => {
    const body = z.object({ content: z.string().trim().min(1).max(16_000) }).safeParse(request.body);
    if (!body.success) return reply.code(400).send({ error: 'Memory content must be between 1 and 16000 characters' });
    try {
      const token = await request.jwtVerify<SessionToken>();
      const memory = await memories.save({ tenantId: token.tenantId, userId: token.sub, content: body.data.content });
      return reply.code(201).send({ data: memory });
    } catch (error) {
      if (error instanceof Error && error.message === 'Memory limit reached') return reply.code(409).send({ error: 'Batas memory untuk plan aktif sudah tercapai' });
      if (error instanceof Error && error.message === 'Memory limit reached') return reply.code(409).send({ error: 'Batas memory plan aktif sudah tercapai' });
      if (error instanceof Error && error.message === 'Embedding provider is not configured') return reply.code(503).send({ error: 'Memory service is not configured' });
      throw error;
    }
  });
  app.post('/v1/actions', { config: { rateLimit: { max: 30, timeWindow: '1 minute' } } }, async (request, reply) => {
    const body = z.object({ action: z.enum(['ask', 'update', 'delete']), text: z.string().trim().min(1).max(4_000) }).safeParse(request.body);
    if (!body.success) return reply.code(400).send({ error: 'Action must be ask|update|delete with non-empty text' });
    try {
      const token = await request.jwtVerify<SessionToken>();
      return reply.send({ data: await memories.act({ tenantId: token.tenantId, userId: token.sub, action: body.data.action, text: body.data.text }) });
    } catch (error) { if (error instanceof Error && error.message.includes('provider is not configured')) return reply.code(503).send({ error: 'Action service is not configured' }); throw error; }
  });
  return app;
}
