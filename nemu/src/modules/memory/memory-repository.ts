import type { Pool } from 'pg';
import type { EncryptedValue } from '../security/encryption.js';

export type MemoryRow = { id: string; title: string | null; category: string | null; encrypted_content: Buffer; encryption_iv: Buffer; encryption_tag: Buffer; searchable_metadata: Record<string, unknown>; source: string; created_at: Date; updated_at: Date };
export class MemoryRepository {
  public constructor(private readonly db: Pool) {}
  async create(input: { tenantId: string; userId: string; title: string; category: string; encrypted: EncryptedValue; metadata: Record<string, unknown>; embedding: number[] }) {
    const vector = `[${input.embedding.join(',')}]`;
    const result = await this.db.query<MemoryRow>(
      `INSERT INTO memories (tenant_id, user_id, title, category, encrypted_content, encryption_iv, encryption_tag, searchable_metadata, search_document, embedding)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8::jsonb, to_tsvector('simple', $9), $10::vector)
       RETURNING id, title, category, encrypted_content, encryption_iv, encryption_tag, searchable_metadata, source, created_at, updated_at`,
      [input.tenantId, input.userId, input.title, input.category, input.encrypted.ciphertext, input.encrypted.iv, input.encrypted.tag, JSON.stringify(input.metadata), `${input.title} ${Object.values(input.metadata).join(' ')}`, vector]);
    await this.db.query(`INSERT INTO memory_revisions (memory_id, tenant_id, actor_user_id, operation, encrypted_content, encryption_iv, encryption_tag) VALUES ($1, $2, $3, 'create', $4, $5, $6)`, [result.rows[0]!.id, input.tenantId, input.userId, input.encrypted.ciphertext, input.encrypted.iv, input.encrypted.tag]);
    return result.rows[0]!;
  }
  async list(tenantId: string, limit: number): Promise<MemoryRow[]> {
    const result = await this.db.query<MemoryRow>(`SELECT id, title, category, encrypted_content, encryption_iv, encryption_tag, searchable_metadata, source, created_at, updated_at FROM memories WHERE tenant_id = $1 AND deleted_at IS NULL ORDER BY created_at DESC LIMIT $2`, [tenantId, limit]);
    return result.rows;
  }
  /** Soft-deletes a memory (sets deleted_at). No-op when already deleted or missing. */
  async softDelete(input: { tenantId: string; userId: string; memoryId: string }): Promise<boolean> {
    const result = await this.db.query(`UPDATE memories SET deleted_at = now(), updated_at = now() WHERE id = $1 AND tenant_id = $2 AND deleted_at IS NULL`, [input.memoryId, input.tenantId]);
    if (result.rowCount) await this.db.query(`INSERT INTO memory_revisions (memory_id, tenant_id, actor_user_id, operation) VALUES ($1, $2, $3, 'delete')`, [input.memoryId, input.tenantId, input.userId]);
    return (result.rowCount ?? 0) > 0;
  }
  /** Replaces a memory's ciphertext/metadata/embedding. Returns the row, or null when missing or already deleted. */
  async update(input: { tenantId: string; userId: string; memoryId: string; title: string; category: string; encrypted: EncryptedValue; metadata: Record<string, unknown>; embedding: number[] }): Promise<MemoryRow | null> {
    const vector = `[${input.embedding.join(',')}]`;
    const result = await this.db.query<MemoryRow>(
      `UPDATE memories SET title = $3, category = $4, encrypted_content = $5, encryption_iv = $6, encryption_tag = $7, searchable_metadata = $8::jsonb, search_document = to_tsvector('simple', $9), embedding = $10::vector, updated_at = now()
       WHERE id = $1 AND tenant_id = $2 AND deleted_at IS NULL
       RETURNING id, title, category, encrypted_content, encryption_iv, encryption_tag, searchable_metadata, source, created_at, updated_at`,
      [input.memoryId, input.tenantId, input.title, input.category, input.encrypted.ciphertext, input.encrypted.iv, input.encrypted.tag, JSON.stringify(input.metadata), `${input.title} ${Object.values(input.metadata).join(' ')}`, vector]);
    if (!result.rowCount) return null;
    await this.db.query(`INSERT INTO memory_revisions (memory_id, tenant_id, actor_user_id, operation, encrypted_content, encryption_iv, encryption_tag) VALUES ($1, $2, $3, 'update', $4, $5, $6)`, [input.memoryId, input.tenantId, input.userId, input.encrypted.ciphertext, input.encrypted.iv, input.encrypted.tag]);
    return result.rows[0]!;
  }
  /** Restores a soft-deleted memory. No-op when already active or missing. */
  async restore(input: { tenantId: string; userId: string; memoryId: string }): Promise<boolean> {
    const result = await this.db.query(`UPDATE memories SET deleted_at = NULL, updated_at = now() WHERE id = $1 AND tenant_id = $2 AND deleted_at IS NOT NULL`, [input.memoryId, input.tenantId]);
    if (result.rowCount) await this.db.query(`INSERT INTO memory_revisions (memory_id, tenant_id, actor_user_id, operation) VALUES ($1, $2, $3, 'restore')`, [input.memoryId, input.tenantId, input.userId]);
    return (result.rowCount ?? 0) > 0;
  }
  /** Lists soft-deleted memories (trash), newest first. */
  async trashList(tenantId: string, limit: number): Promise<MemoryRow[]> {
    const result = await this.db.query<MemoryRow>(`SELECT id, title, category, encrypted_content, encryption_iv, encryption_tag, searchable_metadata, source, created_at, updated_at FROM memories WHERE tenant_id = $1 AND deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT $2`, [tenantId, limit]);
    return result.rows;
  }
  async lexicalSearch(tenantId: string, query: string, limit: number): Promise<Array<MemoryRow & { lexical_score: number }>> {
    const result = await this.db.query<MemoryRow & { lexical_score: number }>(`SELECT id, title, category, encrypted_content, encryption_iv, encryption_tag, searchable_metadata, source, created_at, updated_at, ts_rank_cd(search_document, websearch_to_tsquery('simple', $2)) AS lexical_score FROM memories WHERE tenant_id = $1 AND deleted_at IS NULL AND search_document @@ websearch_to_tsquery('simple', $2) ORDER BY lexical_score DESC, updated_at DESC LIMIT $3`, [tenantId, query, limit]);
    return result.rows;
  }
  async vectorSearch(tenantId: string, embedding: number[], limit: number): Promise<Array<MemoryRow & { distance: number }>> {
    const vector = `[${embedding.join(',')}]`;
    const result = await this.db.query<MemoryRow & { distance: number }>(`SELECT id, title, category, encrypted_content, encryption_iv, encryption_tag, searchable_metadata, source, created_at, updated_at, embedding <=> $2::vector AS distance FROM memories WHERE tenant_id = $1 AND deleted_at IS NULL AND embedding IS NOT NULL ORDER BY embedding <=> $2::vector LIMIT $3`, [tenantId, vector, limit]);
    return result.rows;
  }
}
