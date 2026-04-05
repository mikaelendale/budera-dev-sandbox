import { NextRequest } from 'next/server';

import { requireBankSecret } from '@/lib/auth';
import { processBookTransfer } from '@/lib/transfers/engine';

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
  const fromId = typeof body.from_account_id === 'string' ? body.from_account_id : undefined;
  const toId = typeof body.to_account_id === 'string' ? body.to_account_id : undefined;
  const amountCents = typeof body.amount_cents === 'number' ? body.amount_cents : undefined;
  if (!fromId || !toId || amountCents === undefined) {
    return Response.json({ error: 'invalid_body' }, { status: 400 });
  }
  try {
    const { transfer, duplicate } = processBookTransfer({
      from_account_id: fromId,
      to_account_id: toId,
      amount_cents: amountCents,
      idempotency_key: typeof body.idempotency_key === 'string' ? body.idempotency_key : undefined,
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
    if (code === 'currency_mismatch' || code === 'insufficient_funds' || code === 'invalid_amount') {
      return Response.json({ error: code }, { status: 400 });
    }
    return Response.json({ error: code }, { status: 400 });
  }
}
