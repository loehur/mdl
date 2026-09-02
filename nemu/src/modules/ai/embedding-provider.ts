export interface EmbeddingProvider { embed(input: string): Promise<number[]>; }

export class OpenAiEmbeddingProvider implements EmbeddingProvider {
  public constructor(private readonly apiKey: string | undefined, private readonly model: string, private readonly dimensions: number) {}
  async embed(input: string): Promise<number[]> {
    if (!this.apiKey) throw new Error('Embedding provider is not configured');
    const response = await fetch('https://api.openai.com/v1/embeddings', { method: 'POST', headers: { Authorization: `Bearer ${this.apiKey}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ model: this.model, input, dimensions: this.dimensions }) });
    if (!response.ok) throw new Error(`Embedding provider failed with status ${response.status}`);
    const body = await response.json() as { data?: Array<{ embedding?: number[] }> };
    const embedding = body.data?.[0]?.embedding;
    if (!embedding || embedding.length !== this.dimensions) throw new Error('Embedding provider returned an invalid vector');
    return embedding;
  }
}
