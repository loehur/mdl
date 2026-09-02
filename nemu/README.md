# NEMU API

Backend foundation for NEMU, a multi-tenant personal AI memory service. This milestone intentionally contains no UI.

## Architecture

- **Fastify + TypeScript**: REST API with request limits, CORS allow-list, security headers, and redacted authorization/token logs.
- **PostgreSQL + pgvector**: tenants, users, memberships, encrypted memories, and immutable memory revisions are migrated through SQL files.
- **Tenant boundary**: every memory and revision stores `tenant_id`. Future repositories must accept an authenticated `tenantId` and place it in each SQL predicate; client supplied tenant IDs are never trusted.
- **Google identity and session**: the backend validates Google ID tokens, provisions a personal tenant inside one database transaction, then issues a short-lived signed NEMU JWT. Protected routes derive user, tenant, and role exclusively from that JWT.
- **Memory save**: `POST /v1/memories` derives minimal searchable metadata, obtains an embedding through a provider interface, encrypts original content with AES-256-GCM, and writes the ciphertext plus a create revision.

## Local setup

1. Copy `.env.example` to `.env` and set the values. Do not commit `.env`.
2. Start PostgreSQL with pgvector:

   ```sh
   docker compose up -d
   ```

3. Install and migrate:

   ```sh
   npm install
   npm run migrate
   npm run dev
   ```

`GET /health` returns the API status. `POST /v1/auth/session` accepts `{ "idToken": "<Google ID token>" }`; it verifies the configured Google OAuth audience and returns server-resolved membership plus a 15-minute access token. Send that token in `Authorization: Bearer <token>` to use `POST /v1/memories` and `GET /v1/memories`.

## Docker stack

The Compose stack starts PostgreSQL 16 with pgvector, performs migrations once, then starts the API and the web app. Copy `.env.docker.example` to `.env.docker`, set all secrets, then run:

```sh
docker compose up --build
```

- Web app: `http://localhost:8080`
- API health: `http://localhost:3001/health`
- PostgreSQL host port: `localhost:5434`

The web container proxies `/api/*` to the API inside Docker. Do not expose this Compose setup to the internet with its sample values; set a strong database password, a random 32-byte base64 encryption key, a 32+ character JWT secret, and production CORS/Google OAuth values first.

## Database and vector policy

The initial `embedding vector` column deliberately has no fixed dimension. An HNSW index must be created only in a dedicated future migration after selecting the embedding model and its exact dimensions (configured through `EMBEDDING_DIMENSIONS`). This avoids an unsafe hard-coded vector size or an invalid index when providers change.

All future lexical and vector queries must include `tenant_id = $tenantId` in their query itself, as well as `deleted_at IS NULL` for normal retrieval. A global vector search followed by application filtering is prohibited.

## Encryption design

Memory plaintext is encrypted with AES-256-GCM. `MEMORY_ENCRYPTION_KEY_BASE64` must decode to 32 bytes and is supplied only to the backend via a secret manager/environment configuration. Ciphertext, IV, and authentication tag are stored separately in PostgreSQL; the key is never stored there. Embeddings and searchable metadata are **not encrypted** and may leak semantic information, so they must be treated as sensitive and minimized. Decrypted content is never written to application logs.

The embedding integration is defined behind `EmbeddingProvider`; the current `OpenAiEmbeddingProvider` is selected by environment configuration and never exposes its API key to the client. The provider must be configured before memory creation is enabled.

## Chat provider fallback

The ASK pipeline uses a `ChatProvider` chain: `DEEPSEEK_MODEL=deepseek-chat` is primary, while `OPENAI_MODEL=gpt-5.6-luna` is the fallback. The fallback runs only for retryable primary failures (network failure, timeout, rate limit, or 5xx); an invalid credential or malformed request fails explicitly rather than hiding a configuration issue. Set both provider keys only in backend secrets. GPT-5.6 Luna is supported by OpenAI's Responses API and is intended for cost-sensitive, high-volume work. [OpenAI model documentation](https://developers.openai.com/api/docs/models/gpt-5.6-luna)

## Required environment variables

See `.env.example` for the complete list. Production must set explicit `CORS_ORIGINS`, valid Google client IDs, a managed PostgreSQL connection, an independent encryption secret, and AI provider credentials only on the backend.

## Verification

```sh
npm run build
npm test
```

The automated tests cover configuration parsing and the unauthenticated health endpoint. Database migration verification requires a PostgreSQL server with the pgvector extension.
