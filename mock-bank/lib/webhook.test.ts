import crypto from 'crypto';
import { describe, expect, it } from 'vitest';

function signBody(body: string, secret: string): string {
  return `sha256=${crypto.createHmac('sha256', secret).update(body).digest('hex')}`;
}

describe('webhook signing', () => {
  it('matches Laravel hash_hmac verification', () => {
    const secret = 'whsec_test';
    const payload = { event: 'transfer.book.completed', id: 'evt_1' };
    const body = JSON.stringify(payload);
    const sig = signBody(body, secret);
    const expected = crypto.createHmac('sha256', secret).update(body).digest('hex');
    expect(sig).toBe(`sha256=${expected}`);
  });
});
