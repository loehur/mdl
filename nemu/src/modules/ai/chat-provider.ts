export type ChatRequest = { instructions: string; input: string; maxOutputTokens?: number };
export interface ChatProvider { answer(request: ChatRequest): Promise<string>; }

export class ChatProviderError extends Error {
  public constructor(message: string, public readonly status?: number) { super(message); }
  get retryable() { return this.status === undefined || this.status === 408 || this.status === 429 || this.status >= 500; }
}

export class DeepSeekChatProvider implements ChatProvider {
  public constructor(private readonly apiKey: string | undefined, private readonly model: string, private readonly baseUrl: string) {}
  async answer(request: ChatRequest): Promise<string> {
    if (!this.apiKey) throw new ChatProviderError('DeepSeek provider is not configured', 401);
    const response = await fetch(`${this.baseUrl.replace(/\/$/, '')}/v1/chat/completions`, { method: 'POST', headers: { Authorization: `Bearer ${this.apiKey}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ model: this.model, messages: [{ role: 'system', content: request.instructions }, { role: 'user', content: request.input }], max_tokens: request.maxOutputTokens ?? 400, temperature: 0.2 }) });
    if (!response.ok) throw new ChatProviderError('DeepSeek chat request failed', response.status);
    const body = await response.json() as { choices?: Array<{ message?: { content?: string } }> };
    const text = body.choices?.[0]?.message?.content?.trim();
    if (!text) throw new ChatProviderError('DeepSeek returned an empty answer', 502);
    return text;
  }
}

export class OpenAiChatProvider implements ChatProvider {
  public constructor(private readonly apiKey: string | undefined, private readonly model: string) {}
  async answer(request: ChatRequest): Promise<string> {
    if (!this.apiKey) throw new ChatProviderError('OpenAI provider is not configured', 401);
    const response = await fetch('https://api.openai.com/v1/responses', { method: 'POST', headers: { Authorization: `Bearer ${this.apiKey}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ model: this.model, instructions: request.instructions, input: request.input, max_output_tokens: request.maxOutputTokens ?? 400 }) });
    if (!response.ok) throw new ChatProviderError('OpenAI chat request failed', response.status);
    const body = await response.json() as { output_text?: string };
    const text = body.output_text?.trim();
    if (!text) throw new ChatProviderError('OpenAI returned an empty answer', 502);
    return text;
  }
}

export class FallbackChatProvider implements ChatProvider {
  public constructor(private readonly primary: ChatProvider, private readonly fallback: ChatProvider, private readonly onFallback?: () => void) {}
  async answer(request: ChatRequest): Promise<string> {
    try { return await this.primary.answer(request); }
    catch (error) {
      if (!(error instanceof ChatProviderError) || !error.retryable) throw error;
      this.onFallback?.();
      return this.fallback.answer(request);
    }
  }
}
