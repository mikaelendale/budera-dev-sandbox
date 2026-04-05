import { NextRequest } from 'next/server';
import { runSettlement } from '@/lib/settlement';
import { getTransfer } from '@/lib/store';

export async function POST(request: NextRequest) {
  let ref: string | undefined;
  try {
    const body = await request.json();
    ref = typeof body?.ref === 'string' ? body.ref : undefined;
  } catch {
    return Response.json({ error: 'invalid_json' }, { status: 400 });
  }
  if (!ref) {
    return Response.json({ error: 'ref_required' }, { status: 400 });
  }
  const t = getTransfer(ref);
  if (!t || t.rail !== 'ach') {
    return Response.json({ error: 'transfer_not_found' }, { status: 404 });
  }
  if (t.status !== 'pending') {
    return Response.json(
      { error: 'transfer_not_pending', status: t.status },
      { status: 422 },
    );
  }
  runSettlement(ref, { forceSuccess: true });
  return Response.json({ ok: true, ref });
}
