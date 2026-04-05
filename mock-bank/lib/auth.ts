import { NextRequest } from 'next/server';

export function requireBankSecret(request: NextRequest): Response | null {
  const secret = process.env.MOCK_BANK_SECRET;
  if (!secret) {
    return null;
  }
  const header =
    request.headers.get('x-bank-secret') ??
    request.headers.get('authorization')?.replace(/^Bearer\s+/i, '') ??
    '';
  if (header !== secret) {
    return new Response(JSON.stringify({ error: 'unauthorized' }), {
      status: 401,
      headers: { 'Content-Type': 'application/json' },
    });
  }
  return null;
}
