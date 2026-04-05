import { randomUUID } from 'crypto';

import type { TransferRecord } from './transfers/types';

export type Account = {
  id: string;
  currency: string;
  balanceCents: number;
  createdAt: string;
};

/** Next.js can load multiple copies of this module in dev; keep one store on globalThis. */
const STORE_KEY = '__buderaMockBankStoreV1';

type StoreBuckets = {
  accounts: Map<string, Account>;
  transfers: Map<string, TransferRecord>;
  idempotency: Map<string, string>;
  failNextAch: boolean;
  failNextWire: boolean;
  failNextCheckClear: boolean;
};

function buckets(): StoreBuckets {
  const g = globalThis as unknown as Record<string, StoreBuckets>;
  if (!g[STORE_KEY]) {
    g[STORE_KEY] = {
      accounts: new Map(),
      transfers: new Map(),
      idempotency: new Map(),
      failNextAch: false,
      failNextWire: false,
      failNextCheckClear: false,
    };
  }
  return g[STORE_KEY];
}

export function getFailNextAch(): boolean {
  return buckets().failNextAch;
}

export function setFailNextAch(value: boolean): void {
  buckets().failNextAch = value;
}

export function getFailNextWire(): boolean {
  return buckets().failNextWire;
}

export function setFailNextWire(value: boolean): void {
  buckets().failNextWire = value;
}

export function getFailNextCheckClear(): boolean {
  return buckets().failNextCheckClear;
}

export function setFailNextCheckClear(value: boolean): void {
  buckets().failNextCheckClear = value;
}

function randomId(prefix: string): string {
  return `${prefix}_${randomUUID().replace(/-/g, '').slice(0, 16)}`;
}

export function createAccount(currency = 'USD'): Account {
  const { accounts } = buckets();
  const id = randomId('acct');
  const acc: Account = {
    id,
    currency,
    balanceCents: 0,
    createdAt: new Date().toISOString(),
  };
  accounts.set(id, acc);
  return acc;
}

export function getAccount(id: string): Account | undefined {
  return buckets().accounts.get(id);
}

/**
 * Return an existing account or create one with the given id (dev/sandbox convenience).
 * Avoids 404s when Budera still references a partner id after mock-bank restart or DB drift.
 */
export function ensureAccountWithId(id: string, currency = 'USD'): Account {
  const { accounts } = buckets();
  const existing = accounts.get(id);
  if (existing) {
    return existing;
  }
  const acc: Account = {
    id,
    currency,
    balanceCents: 0,
    createdAt: new Date().toISOString(),
  };
  accounts.set(id, acc);

  return acc;
}

export function listAccounts(): Account[] {
  return [...buckets().accounts.values()];
}

export function putTransfer(t: TransferRecord): void {
  buckets().transfers.set(t.id, t);
}

export function getTransfer(id: string): TransferRecord | undefined {
  return buckets().transfers.get(id);
}

export function listTransfers(): TransferRecord[] {
  return [...buckets().transfers.values()];
}

export function findIdempotency(key: string): string | undefined {
  return buckets().idempotency.get(key);
}

export function registerIdempotency(key: string, transferId: string): void {
  buckets().idempotency.set(key, transferId);
}

/** Positive delta credits the account; negative debits. */
export function applyBalanceDelta(accountId: string, deltaCents: number): void {
  const acc = buckets().accounts.get(accountId);
  if (!acc) {
    return;
  }
  acc.balanceCents += deltaCents;
}
