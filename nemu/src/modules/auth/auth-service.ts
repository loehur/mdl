import type { Pool } from 'pg';
import type { GoogleIdentity } from './google-verifier.js';

export type SessionIdentity = { userId: string; tenantId: string; role: 'owner' | 'admin' | 'member'; email: string; displayName?: string; avatarUrl?: string };

export class AuthService {
  public constructor(private readonly db: Pool) {}

  async resolveOrProvision(identity: GoogleIdentity): Promise<SessionIdentity> {
    const client = await this.db.connect();
    try {
      await client.query('BEGIN');
      const user = await client.query<{ id: string }>(
        `INSERT INTO users (google_subject, email, display_name, avatar_url)
         VALUES ($1, $2, $3, $4)
         ON CONFLICT (google_subject) DO UPDATE SET email = EXCLUDED.email, display_name = EXCLUDED.display_name, avatar_url = EXCLUDED.avatar_url, updated_at = now()
         RETURNING id`, [identity.subject, identity.email, identity.displayName ?? null, identity.avatarUrl ?? null]);
      const userId = user.rows[0]!.id;
      const membership = await client.query<{ tenant_id: string; role: SessionIdentity['role'] }>(
        'SELECT tenant_id, role FROM tenant_memberships WHERE user_id = $1 ORDER BY created_at LIMIT 1', [userId]);
      if (membership.rowCount) {
        await client.query('COMMIT');
        return { userId, tenantId: membership.rows[0]!.tenant_id, role: membership.rows[0]!.role, email: identity.email, displayName: identity.displayName, avatarUrl: identity.avatarUrl };
      }
      const tenant = await client.query<{ id: string }>('INSERT INTO tenants (name) VALUES ($1) RETURNING id', [`${identity.displayName ?? identity.email}'s workspace`]);
      const tenantId = tenant.rows[0]!.id;
      await client.query('INSERT INTO tenant_memberships (tenant_id, user_id, role) VALUES ($1, $2, $3)', [tenantId, userId, 'owner']);
      await client.query('COMMIT');
      return { userId, tenantId, role: 'owner', email: identity.email, displayName: identity.displayName, avatarUrl: identity.avatarUrl };
    } catch (error) { await client.query('ROLLBACK'); throw error; } finally { client.release(); }
  }
  async getProfile(userId: string): Promise<Pick<SessionIdentity, 'email' | 'displayName' | 'avatarUrl'>> {
    const result = await this.db.query<{ email: string; display_name: string | null; avatar_url: string | null }>('SELECT email, display_name, avatar_url FROM users WHERE id = $1', [userId]);
    if (!result.rowCount) throw new Error('User not found');
    return { email: result.rows[0]!.email, displayName: result.rows[0]!.display_name ?? undefined, avatarUrl: result.rows[0]!.avatar_url ?? undefined };
  }
}
