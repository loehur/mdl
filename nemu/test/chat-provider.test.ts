import { describe, expect, it, vi } from 'vitest';
import { ChatProviderError, FallbackChatProvider, type ChatProvider } from '../src/modules/ai/chat-provider.js';

describe('FallbackChatProvider', () => {
  const request = { instructions: 'Jawab singkat.', input: 'Apa IP kasir?' };
  it('uses OpenAI fallback after a retryable DeepSeek failure', async () => {
    const primary: ChatProvider = { answer: vi.fn().mockRejectedValue(new ChatProviderError('unavailable', 503)) };
    const fallback: ChatProvider = { answer: vi.fn().mockResolvedValue('192.168.1.20') };
    const provider = new FallbackChatProvider(primary, fallback);
    await expect(provider.answer(request)).resolves.toBe('192.168.1.20');
    expect(fallback.answer).toHaveBeenCalledWith(request);
  });
  it('does not fall back for an invalid DeepSeek credential', async () => {
    const primary: ChatProvider = { answer: vi.fn().mockRejectedValue(new ChatProviderError('unauthorized', 401)) };
    const fallback: ChatProvider = { answer: vi.fn() };
    await expect(new FallbackChatProvider(primary, fallback).answer(request)).rejects.toThrow('unauthorized');
    expect(fallback.answer).not.toHaveBeenCalled();
  });
});
