import { describe, expect, it, vi } from 'vitest';
import type { ChatProvider } from '../src/modules/ai/chat-provider.js';
import type { EmbeddingProvider } from '../src/modules/ai/embedding-provider.js';
import { MemoryService } from '../src/modules/memory/memory-service.js';
import { MemoryEncryption } from '../src/modules/security/encryption.js';

const encryption = new MemoryEncryption(Buffer.alloc(32, 3).toString('base64'));
const embeddings: EmbeddingProvider = { embed: async () => [0.1, 0.2] };

function rowFor(content: string, id = 'memory-1') {
  const encrypted = encryption.encrypt(content);
  return { id, title: content, category: 'General', encrypted_content: encrypted.ciphertext, encryption_iv: encrypted.iv, encryption_tag: encrypted.tag, searchable_metadata: {}, source: 'manual', created_at: new Date(), updated_at: new Date() };
}

/** Builds a service whose semantic retrieval always returns the given rows in order. */
function serviceWithRows(rows: Array<ReturnType<typeof rowFor>>, chat?: ChatProvider) {
  const repository = {
    list: async () => rows,
    vectorSearch: async () => rows.map((row) => ({ ...row, distance: 0.15 })),
    softDelete: vi.fn(async () => true),
    update: vi.fn(async (input: { memoryId: string; title: string; encrypted: { ciphertext: Buffer; iv: Buffer; tag: Buffer } }) => ({ id: input.memoryId, title: input.title, category: 'General', encrypted_content: input.encrypted.ciphertext, encryption_iv: input.encrypted.iv, encryption_tag: input.encrypted.tag, searchable_metadata: {}, source: 'manual', created_at: new Date(), updated_at: new Date() }))
  };
  const service = new MemoryService(repository as never, encryption, embeddings, chat);
  return { service, repository };
}

describe('MemoryService.act pipeline', () => {
  it('executes delete only when AI confirms a relevant match', async () => {
    const chat: ChatProvider = { answer: async () => JSON.stringify({ match: 0, reply: 'Oke, catatan mobil Vios itu sudah aku hapus.' }) };
    const { service, repository } = serviceWithRows([rowFor('Mobil saya toyota vios', 'vios')], chat);
    const result = await service.act({ tenantId: 'tenant-a', userId: 'user-a', action: 'delete', text: 'mobil vios' });
    expect(result.ok).toBe(true);
    expect(repository.softDelete).toHaveBeenCalledWith({ tenantId: 'tenant-a', userId: 'user-a', memoryId: 'vios' });
    expect(result.reply).toContain('hapus');
  });

  it('skips delete when AI judges no candidate is relevant', async () => {
    const chat: ChatProvider = { answer: async () => JSON.stringify({ match: -1, reply: 'Sepertinya belum ada catatan soal itu, jadi tidak ada yang aku hapus.' }) };
    const { service, repository } = serviceWithRows([rowFor('Nama saya PeeKay', 'self')], chat);
    const result = await service.act({ tenantId: 'tenant-a', userId: 'user-a', action: 'delete', text: 'mobil vios' });
    expect(result.ok).toBe(false);
    expect(repository.softDelete).not.toHaveBeenCalled();
    expect(result.reply).toContain('belum ada');
  });

  it('executes update with the new value when AI confirms the target', async () => {
    const chat: ChatProvider = { answer: async () => JSON.stringify({ match: 0, value: 'nama saya Anton', reply: 'Sip, nama kamu sudah aku ubah jadi Anton.' }) };
    const { service, repository } = serviceWithRows([rowFor('Nama saya PeeKay', 'self')], chat);
    const result = await service.act({ tenantId: 'tenant-a', userId: 'user-a', action: 'update', text: 'nama saya jadi Anton' });
    expect(result.ok).toBe(true);
    expect(repository.update).toHaveBeenCalledWith(expect.objectContaining({ memoryId: 'self' }));
    expect((repository.update as ReturnType<typeof vi.fn>).mock.calls[0]![0].title).toContain('Anton');
  });

  it('skips update when AI judges the target irrelevant', async () => {
    const chat: ChatProvider = { answer: async () => JSON.stringify({ match: -1, reply: 'Aku tidak menemukan catatan yang cocok untuk diubah.' }) };
    const { service, repository } = serviceWithRows([rowFor('Nama saya PeeKay', 'self')], chat);
    const result = await service.act({ tenantId: 'tenant-a', userId: 'user-a', action: 'update', text: 'ganti warna cat jadi biru' });
    expect(result.ok).toBe(false);
    expect(repository.update).not.toHaveBeenCalled();
  });

  it('answers naturally from the matched candidate for ask', async () => {
    const chat: ChatProvider = { answer: async () => JSON.stringify({ match: 0, reply: 'Nama kamu adalah PeeKay.' }) };
    const { service } = serviceWithRows([rowFor('Nama saya PeeKay', 'self')], chat);
    const result = await service.act({ tenantId: 'tenant-a', userId: 'user-a', action: 'ask', text: 'siapa nama saya?' });
    expect(result.ok).toBe(true);
    expect(result.memoryId).toBe('self');
    expect(result.reply).toBe('Nama kamu adalah PeeKay.');
  });

  it('answers honestly when no candidate is relevant for ask', async () => {
    const chat: ChatProvider = { answer: async () => JSON.stringify({ match: -1, reply: 'Hmm, aku belum pernah menyimpan catatan soal itu.' }) };
    const { service } = serviceWithRows([rowFor('Nama saya PeeKay', 'self')], chat);
    const result = await service.act({ tenantId: 'tenant-a', userId: 'user-a', action: 'ask', text: 'siapa nama anak saya?' });
    expect(result.ok).toBe(false);
    expect(result.reply).toContain('belum pernah');
  });

  it('returns a human fallback when AI output is unparsable', async () => {
    const chat: ChatProvider = { answer: async () => 'maaf aku tidak mengerti' };
    const { service, repository } = serviceWithRows([rowFor('Mobil saya vios', 'vios')], chat);
    const result = await service.act({ tenantId: 'tenant-a', userId: 'user-a', action: 'delete', text: 'mobil vios' });
    expect(result.ok).toBe(false);
    expect(repository.softDelete).not.toHaveBeenCalled();
    expect(typeof result.reply).toBe('string');
  });
});
