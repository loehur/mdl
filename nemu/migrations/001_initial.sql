CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE tenants (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  kind text NOT NULL DEFAULT 'personal' CHECK (kind IN ('personal', 'team')),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  google_subject text NOT NULL UNIQUE,
  email text NOT NULL,
  display_name text,
  avatar_url text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE tenant_memberships (
  tenant_id uuid NOT NULL REFERENCES tenants(id),
  user_id uuid NOT NULL REFERENCES users(id),
  role text NOT NULL CHECK (role IN ('owner', 'admin', 'member')),
  created_at timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (tenant_id, user_id)
);

CREATE TABLE memories (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id),
  user_id uuid NOT NULL REFERENCES users(id),
  title text,
  category text,
  encrypted_content bytea NOT NULL,
  encryption_iv bytea NOT NULL,
  encryption_tag bytea NOT NULL,
  searchable_metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
  search_document tsvector NOT NULL DEFAULT ''::tsvector,
  embedding vector,
  source text NOT NULL DEFAULT 'manual',
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  deleted_at timestamptz
);

CREATE TABLE memory_revisions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  memory_id uuid NOT NULL REFERENCES memories(id),
  tenant_id uuid NOT NULL REFERENCES tenants(id),
  actor_user_id uuid NOT NULL REFERENCES users(id),
  operation text NOT NULL CHECK (operation IN ('create', 'update', 'delete', 'restore')),
  encrypted_content bytea,
  encryption_iv bytea,
  encryption_tag bytea,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX memories_active_tenant_created_idx ON memories (tenant_id, created_at DESC) WHERE deleted_at IS NULL;
CREATE INDEX memories_active_tenant_user_idx ON memories (tenant_id, user_id) WHERE deleted_at IS NULL;
CREATE INDEX memories_active_search_idx ON memories USING gin (search_document) WHERE deleted_at IS NULL;
CREATE INDEX memories_metadata_idx ON memories USING gin (searchable_metadata);
CREATE INDEX memory_revisions_tenant_memory_idx ON memory_revisions (tenant_id, memory_id, created_at DESC);

-- `embedding` has no fixed dimensions until an embedding model is selected. Add a
-- dimension-specific HNSW index in a later migration after that decision is final.
