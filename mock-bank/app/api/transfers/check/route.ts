import { NextRequest } from 'next/server';

import { requireBankSecret } from '@/lib/auth';
import { processCheckActionByTransferId, processCheckTransfer } from '@/lib/transfers/engine';

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
  const action = body.action === 'stop' || body.action === 'return' ? body.action : 'issue';
  if (action === 'stop' || action === 'return') {
    const transferId = typeof body.transfer_id === 'string' ? body.transfer_id : undefined;
    if (!transferId) {
      return Response.json({ error: 'transfer_id_required' }, { status: 400 });
    }
    try {
      const t = processCheckActionByTransferId(transferId, action);
      return Response.json({
        transfer_id: t.id,
        ref: t.id,
        rail: t.rail,
        status: t.status,
      });
    } catch (e) {
      const code = e instanceof Error ? e.message : 'error';
      if (code === 'transfer_not_found' || code === 'invalid_check_action') {
        return Response.json({ error: code }, { status: 400 });
      }
      return Response.json({ error: code }, { status: 400 });
    }
  }
  const accountId = typeof body.account_id === 'string' ? body.account_id : undefined;
  const amountCents = typeof body.amount_cents === 'number' ? body.amount_cents : undefined;
  const payee = typeof body.payee === 'string' ? body.payee : undefined;
  if (!accountId || amountCents === undefined || !payee) {
    return Response.json({ error: 'invalid_body' }, { status: 400 });
  }
  try {
    const { transfer, duplicate } = processCheckTransfer({
      account_id: accountId,
      amount_cents: amountCents,
      payee,
      memo: typeof body.memo === 'string' ? body.memo : undefined,
      action: 'issue',
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
    if (code === 'insufficient_funds' || code === 'invalid_amount') {
      return Response.json({ error: code }, { status: 400 });
    }
    return Response.json({ error: code }, { status: 400 });
  }
}
