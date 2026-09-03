import { describe, expect, it, vi } from 'vitest';
import type { ChatProvider } from '../src/modules/ai/chat-provider.js';
import type { EmbeddingProvider } from '../src/modules/ai/embedding-provider.js';
import { MemoryService, normalizeQuery } from '../src/modules/memory/memory-service.js';
import { MemoryEncryption } from '../src/modules/security/encryption.js';

const encryption = new MemoryEncryption(Buffer.alloc(32, 7).toString('base64'));
const embeddings: EmbeddingProvider = { embed: async () => [0.1, 0.2] };
function row(content: string, id: string, distance = 0.15, updatedAt = new Date('2026-01-01')) {
  const encrypted = encryption.encrypt(content);
  return { id, title: content, category: 'General', encrypted_content: encrypted.ciphertext, encryption_iv: encrypted.iv, encryption_tag: encrypted.tag, created_at: updatedAt, updated_at: updatedAt, distance };
}
function service(rows: ReturnType<typeof row>[], chat?: ChatProvider) {
  const repository = { vectorSearch: async () => rows, lexicalSearch: async () => rows.map((item, index) => ({ ...item, lexical_score: 1 - index / 10 })) };
  return new MemoryService(repository as never, encryption, embeddings, chat);
}

describe('hybrid Find', () => {
  it.each([
    ['Istri saya Neli', 'sekarang saya penasaran, siapa sebenarnya istri saya?', 'Neli'],
    ['Saya suka minum jeruk', 'kalau saya boleh tanya, minuman apa yang saya suka?', 'jeruk'],
    ['Mobil saya Vios', 'Kendaraan saya apa?', 'Vios'],
    ['IP komputer kasir 192.168.1.20', 'Alamat jaringan PC kasir?', '192.168.1.20']
  ])('fast-path answers a direct fact: %s', async (memory, query, expected) => {
    const chat: ChatProvider = { answer: vi.fn() };
    const result = await service([row(memory, 'fact')], chat).act({ tenantId: 't', userId: 'u', action: 'ask', text: query });
    expect(result.reply).toContain(expected);
    expect(result.memoryId).toBe('fact');
    expect(chat.answer).not.toHaveBeenCalled();
  });

  it('normalizes conversational filler without touching the factual question', () => {
    expect(normalizeQuery('sekarang saya tanya, siapa nama saya?')).toBe('siapa nama saya?');
    expect(normalizeQuery('boleh saya tanya siapa istri saya?')).toBe('siapa istri saya?');
  });

  it('uses AI when two direct facts conflict', async () => {
    const chat: ChatProvider = { answer: vi.fn(async () => JSON.stringify({ match: 1, reply: 'Nama kamu adalah PeeKay.' })) };
    const result = await service([row('Nama saya Luhur Gunawan', 'old', 0.15, new Date('2025-01-01')), row('Ganti nama saya, jadi PeeKay', 'new', 0.16, new Date('2026-01-01'))], chat).act({ tenantId: 't', userId: 'u', action: 'ask', text: 'siapa nama saya?' });
    expect(chat.answer).toHaveBeenCalled();
    expect(result.memoryId).toBe('new');
    expect(result.reply).toContain('PeeKay');
  });
});
