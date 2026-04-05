import { NextRequest } from 'next/server';

import { requireBankSecret } from '@/lib/auth';
import { getTransfer } from '@/lib/store';
import { transferToJson } from '@/lib/transfers/serialize';

type Params = { params: Promise<{ ref: string }> };

export async function GET(request: NextRequest, context: Params) {
  const denied = requireBankSecret(request);
  if (denied) {
    return denied;
  }
  const { ref } = await context.params;
  const t = getTransfer(ref);
  if (!t) {
    return Response.json({ error: 'not_found' }, { status: 404 });
  }
  return Response.json(transferToJson(t));
}
