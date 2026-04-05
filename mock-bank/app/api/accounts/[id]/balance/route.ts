import { NextRequest } from 'next/server';
import { requireBankSecret } from '@/lib/auth';
import { ensureAccountWithId } from '@/lib/store';

type Params = { params: Promise<{ id: string }> };

export async function GET(request: NextRequest, context: Params) {
  const denied = requireBankSecret(request);
  if (denied) {
    return denied;
  }
  const { id } = await context.params;
  const acc = ensureAccountWithId(id);
  return Response.json({
    balance_cents: acc.balanceCents,
    currency: acc.currency,
  });
}
