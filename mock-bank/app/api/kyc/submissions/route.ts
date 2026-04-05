import { NextRequest } from 'next/server';

import { requireBankSecret } from '@/lib/auth';
import { createKycSubmission } from '@/lib/kyc/store';
import { emitKycSubmitted, scheduleKycResolution } from '@/lib/kyc/engine';

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
  const sub = createKycSubmission({
    account_id: typeof body.account_id === 'string' ? body.account_id : undefined,
    legal_name: typeof body.legal_name === 'string' ? body.legal_name : undefined,
    date_of_birth: typeof body.date_of_birth === 'string' ? body.date_of_birth : undefined,
    address_line1: typeof body.address_line1 === 'string' ? body.address_line1 : undefined,
    last4_ssn: typeof body.last4_ssn === 'string' ? body.last4_ssn : undefined,
  });
  emitKycSubmitted(sub);
  scheduleKycResolution(sub);
  return Response.json(
    {
      id: sub.id,
      status: sub.status,
      created_at: sub.createdAt,
    },
    { status: 201 },
  );
}
