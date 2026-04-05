import { NextRequest } from 'next/server';

import { requireBankSecret } from '@/lib/auth';
import { getTransferOrThrow } from '@/lib/transfers/engine';
import { transferToJson } from '@/lib/transfers/serialize';

type Params = { params: Promise<{ id: string }> };

export async function GET(request: NextRequest, context: Params) {
  const denied = requireBankSecret(request);
  if (denied) {
    return denied;
  }
  const { id } = await context.params;
  try {
    const t = getTransferOrThrow(id);
    return Response.json(transferToJson(t));
  } catch {
    return Response.json({ error: 'not_found' }, { status: 404 });
  }
}
