import { describe, expect, it } from 'vitest';
import { MemoryEncryption } from '../src/modules/security/encryption.js';

describe('MemoryEncryption', () => {
  const encryption = new MemoryEncryption(Buffer.alloc(32, 9).toString('base64'));
  it('round-trips plaintext and uses unique ciphertext material', () => {
    const first = encryption.encrypt('IP komputer kasir adalah 192.168.1.20');
    const second = encryption.encrypt('IP komputer kasir adalah 192.168.1.20');
    expect(first.ciphertext.equals(second.ciphertext)).toBe(false);
    expect(encryption.decrypt(first)).toBe('IP komputer kasir adalah 192.168.1.20');
  });
  it('rejects altered ciphertext', () => {
    const encrypted = encryption.encrypt('rahasia');
    encrypted.ciphertext[0] = encrypted.ciphertext[0]! ^ 1;
    expect(() => encryption.decrypt(encrypted)).toThrow();
  });
});
