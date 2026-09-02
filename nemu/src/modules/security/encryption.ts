import { createCipheriv, createDecipheriv, randomBytes } from 'node:crypto';

export type EncryptedValue = { ciphertext: Buffer; iv: Buffer; tag: Buffer };

export class MemoryEncryption {
  private readonly key: Buffer;
  public constructor(keyBase64: string) {
    this.key = Buffer.from(keyBase64, 'base64');
    if (this.key.length !== 32) throw new Error('Memory encryption key must be 32 bytes');
  }

  encrypt(plaintext: string): EncryptedValue {
    const iv = randomBytes(12);
    const cipher = createCipheriv('aes-256-gcm', this.key, iv);
    return { ciphertext: Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]), iv, tag: cipher.getAuthTag() };
  }

  decrypt(value: EncryptedValue): string {
    const decipher = createDecipheriv('aes-256-gcm', this.key, value.iv);
    decipher.setAuthTag(value.tag);
    return Buffer.concat([decipher.update(value.ciphertext), decipher.final()]).toString('utf8');
  }
}
