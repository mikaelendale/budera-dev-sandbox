import { NextRequest } from 'next/server';

import { requireBankSecret } from '@/lib/auth';
import { processWireTransfer } from '@/lib/transfers/engine';

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
  if (!accountId || amountCents === undefined) {
    return Response.json({ error: 'invalid_body' }, { status: 400 });
  }
  try {
    const subtype =
      body.subtype === 'drawdown' || body.subtype === 'outgoing' ? body.subtype : undefined;
    const beneficiary =
      typeof body.beneficiary === 'object' && body.beneficiary !== null
        ? (body.beneficiary as { routing_number?: string; account_number?: string; name?: string })
        : undefined;
    const { transfer, duplicate } = processWireTransfer({
      account_id: accountId,
      amount_cents: amountCents,
      currency: typeof body.currency === 'string' ? body.currency : undefined,
      idempotency_key: typeof body.idempotency_key === 'string' ? body.idempotency_key : undefined,
      subtype,
      beneficiary,
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
