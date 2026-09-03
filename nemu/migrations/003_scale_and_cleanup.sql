-- Unused v1 scaffolding: no endpoint or UI reads revision history, JSON metadata,
-- source, or tenant kind. Remove it rather than retaining encrypted duplicates.
DROP TABLE IF EXISTS memory_revisions;
DROP INDEX IF EXISTS memories_metadata_idx;
ALTER TABLE memories DROP COLUMN IF EXISTS searchable_metadata;
ALTER TABLE memories DROP COLUMN IF EXISTS source;
ALTER TABLE tenants DROP COLUMN IF EXISTS kind;

-- Cosine-distance HNSW index for the 1536-dimensional OpenAI embeddings.
-- The partial predicate keeps soft-deleted memories out of the search graph.
ALTER TABLE memories
  ALTER COLUMN embedding TYPE vector(1536)
  USING embedding::vector(1536);

CREATE INDEX IF NOT EXISTS memories_active_embedding_hnsw_idx
  ON memories USING hnsw (embedding vector_cosine_ops)
  WITH (m = 16, ef_construction = 64)
  WHERE deleted_at IS NULL AND embedding IS NOT NULL;
