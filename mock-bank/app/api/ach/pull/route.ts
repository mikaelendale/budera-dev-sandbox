import { NextRequest } from 'next/server';

import { requireBankSecret } from '@/lib/auth';
import { processAch } from '@/lib/settlement';

export async function POST(request: NextRequest) {
  const denied = requireBankSecret(request);
  if (denied) {
    return denied;
  }
  let body: { account_id?: string; amount_cents?: number; idempotency_key?: string };
  try {
    body = await request.json();
  } catch {
    return Response.json({ error: 'invalid_json' }, { status: 400 });
  }
  if (!body.account_id || typeof body.amount_cents !== 'number') {
    return Response.json({ error: 'invalid_body' }, { status: 400 });
  }
  try {
    const { transfer, duplicate } = processAch({
      account_id: body.account_id,
      amount_cents: body.amount_cents,
      idempotency_key: body.idempotency_key,
      kind: 'pull',
    });
    return Response.json(
      {
        ref: transfer.id,
        transfer_id: transfer.id,
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
