import { NextRequest } from 'next/server';
import { requireBankSecret } from '@/lib/auth';
import { createAccount } from '@/lib/store';
import { sendBuderaWebhook } from '@/lib/webhook';

export async function POST(request: NextRequest) {
  const denied = requireBankSecret(request);
  if (denied) {
    return denied;
  }
  let currency = 'USD';
  try {
    const body = await request.json();
    if (body?.currency && typeof body.currency === 'string') {
      currency = body.currency;
    }
  } catch {
    // empty body
  }
  const acc = createAccount(currency);
  await sendBuderaWebhook({
    event: 'account.created',
    id: `evt_${acc.id}`,
    occurred_at: new Date().toISOString(),
    data: { account_id: acc.id, currency: acc.currency },
  });
  return Response.json(
    {
      id: acc.id,
      currency: acc.currency,
      created_at: acc.createdAt,
    },
    { status: 201 },
  );
}
