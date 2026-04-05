import { NextRequest } from 'next/server';

import { setFailNextAch, setFailNextCheckClear, setFailNextWire } from '@/lib/store';

export async function POST(request: NextRequest) {
  let enabled = true;
  let target: 'ach' | 'wire' | 'check_clear' = 'ach';
  try {
    const body = await request.json();
    if (typeof body?.enabled === 'boolean') {
      enabled = body.enabled;
    }
    if (body?.target === 'wire') {
      target = 'wire';
    }
    if (body?.target === 'check_clear') {
      target = 'check_clear';
    }
  } catch {
    // default
  }
  if (target === 'wire') {
    setFailNextWire(enabled);
    return Response.json({ fail_next_wire: enabled });
  }
  if (target === 'check_clear') {
    setFailNextCheckClear(enabled);
    return Response.json({ fail_next_check_clear: enabled });
  }
  setFailNextAch(enabled);
  return Response.json({ fail_next_ach: enabled });
}
