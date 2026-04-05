import { NextRequest } from 'next/server';

import { requireBankSecret } from '@/lib/auth';
import { processSwiftTransfer } from '@/lib/transfers/engine';

export async function POST(request: NextRequest) {
  const denied = requireBankSecret(request);
  if (denied) {
    return denied;
  }
  let body: Record<string, unknown>;
  try {
    body = await request.json();
  } catch {
    return Response.json({ error: 'invalid_json' }, { status: 400 });
  }
  const accountId = typeof body.account_id === 'string' ? body.account_id : undefined;
  const amountCents = typeof body.amount_cents === 'number' ? body.amount_cents : undefined;
  const bic = typeof body.bic === 'string' ? body.bic : undefined;
  const currency = typeof body.currency === 'string' ? body.currency : undefined;
  if (!accountId || amountCents === undefined || !bic || !currency) {
    return Response.json({ error: 'invalid_body' }, { status: 400 });
  }
  try {
    const { transfer, duplicate } = processSwiftTransfer({
      account_id: accountId,
      amount_cents: amountCents,
      currency,
      bic,
      idempotency_key: typeof body.idempotency_key === 'string' ? body.idempotency_key : undefined,
      beneficiary:
        typeof body.beneficiary === 'object' && body.beneficiary !== null
          ? (body.beneficiary as Record<string, unknown>)
          : undefined,
    });
    return Response.json(
      {
        transfer_id: transfer.id,
        ref: transfer.id,
        rail: transfer.rail,
        status: transfer.status,
        duplicate,
      },
      { status: duplicate ? 200 : 202 },
    );
  } catch (e) {
    const code = e instanceof Error ? e.message : 'error';
    if (code === 'account_not_found') {
      return Response.json({ error: code }, { status: 404 });
    }
    if (code === 'insufficient_funds' || code === 'invalid_amount') {
      return Response.json({ error: code }, { status: 400 });
    }
    return Response.json({ error: code }, { status: 400 });
  }
}
