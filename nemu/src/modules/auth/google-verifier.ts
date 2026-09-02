import { OAuth2Client } from 'google-auth-library';

export type GoogleIdentity = { subject: string; email: string; displayName?: string; avatarUrl?: string };

export class GoogleTokenVerifier {
  private readonly client = new OAuth2Client();
  public constructor(private readonly audience: string[]) {}

  async verify(idToken: string): Promise<GoogleIdentity> {
    const ticket = await this.client.verifyIdToken({ idToken, audience: this.audience });
    const payload = ticket.getPayload();
    if (!payload?.sub || !payload.email || !payload.email_verified) throw new Error('Google token does not contain a verified email');
    return { subject: payload.sub, email: payload.email, displayName: payload.name, avatarUrl: payload.picture };
  }
}
