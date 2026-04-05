import { NextRequest } from 'next/server';
import { runAchReturn } from '@/lib/transfers/engine';

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
  const result = runAchReturn(ref);
  if (!result.ok) {
    return Response.json({ error: result.error }, { status: 422 });
  }
  return Response.json({ ok: true, ref });
}
