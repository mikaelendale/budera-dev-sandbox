import { beforeEach, describe, expect, it } from 'vitest';

import { applyBalanceDelta, createAccount, ensureAccountWithId, getAccount } from './store';

describe('store', () => {
  beforeEach(() => {
    // module is singleton; tests only validate delta semantics on fresh account
  });

  it('applyBalanceDelta updates balance', () => {
    const a = createAccount('USD');
    expect(getAccount(a.id)?.balanceCents).toBe(0);
    applyBalanceDelta(a.id, 500);
    expect(getAccount(a.id)?.balanceCents).toBe(500);
    applyBalanceDelta(a.id, -200);
    expect(getAccount(a.id)?.balanceCents).toBe(300);
  });

  it('ensureAccountWithId creates missing accounts with stable id', () => {
    const id = 'acct_stale_partner_ref';
    const first = ensureAccountWithId(id, 'USD');
    expect(first.id).toBe(id);
    expect(ensureAccountWithId(id).balanceCents).toBe(0);
  });
});
