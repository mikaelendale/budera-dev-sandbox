import { NextRequest } from 'next/server';

import { requireBankSecret } from '@/lib/auth';
import { getKycSubmission } from '@/lib/kyc/store';

type Params = { params: Promise<{ id: string }> };

export async function GET(request: NextRequest, context: Params) {
  const denied = requireBankSecret(request);
  if (denied) {
    return denied;
  }
  const { id } = await context.params;
  const row = getKycSubmission(id);
  if (!row) {
    return Response.json({ error: 'not_found' }, { status: 404 });
  }
  return Response.json({
    id: row.id,
    status: row.status,
    created_at: row.createdAt,
    resolved_at: row.resolvedAt ?? null,
    account_id: row.account_id ?? null,
    payload: row.payload,
  });
}
