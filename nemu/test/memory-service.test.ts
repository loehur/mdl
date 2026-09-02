import { describe, expect, it } from 'vitest';
import { MemoryEncryption } from '../src/modules/security/encryption.js';
import { MemoryService } from '../src/modules/memory/memory-service.js';
import type { EmbeddingProvider } from '../src/modules/ai/embedding-provider.js';

describe('MemoryService', () => {
  it('passes tenant scope and never gives plaintext to persistence', async () => {
    let captured: Record<string, unknown> | undefined;
    const repository = { getPlan: async () => 'free' as const, count: async () => 0, create: async (value: Record<string, unknown>) => { captured = value; return { id: 'memory-1', title: value.title, category: value.category, encrypted_content: (value.encrypted as { ciphertext: Buffer }).ciphertext, encryption_iv: (value.encrypted as { iv: Buffer }).iv, encryption_tag: (value.encrypted as { tag: Buffer }).tag, source: 'manual', created_at: new Date('2026-01-01'), updated_at: new Date('2026-01-01') }; }, list: async () => [] };
    const embeddings: EmbeddingProvider = { embed: async () => [0.1, 0.2] };
    const service = new MemoryService(repository as never, new MemoryEncryption(Buffer.alloc(32, 5).toString('base64')), embeddings);
    const result = await service.save({ tenantId: 'tenant-a', userId: 'user-a', content: 'IP komputer kasir adalah 192.168.1.20' });
    expect(captured?.tenantId).toBe('tenant-a');
    expect(JSON.stringify(captured)).not.toContain('IP komputer kasir adalah 192.168.1.20');
    expect(result.content).toContain('192.168.1.20');
  });
});
