import { randomTransferId } from '@/lib/ids';
import {
  applyBalanceDelta,
  ensureAccountWithId,
  findIdempotency,
  getFailNextAch,
  getFailNextCheckClear,
  getFailNextWire,
  getTransfer,
  putTransfer,
  registerIdempotency,
  setFailNextAch,
  setFailNextCheckClear,
  setFailNextWire,
} from '@/lib/store';
import { sendBuderaWebhook } from '@/lib/webhook';

import type { AchDirection, TransferRecord } from './types';

function nowIso(): string {
  return new Date().toISOString();
}

function emit(event: string, data: Record<string, unknown>): void {
  void sendBuderaWebhook({
    event,
    id: `evt_${randomTransferId()}`,
    occurred_at: nowIso(),
    data,
  });
}

const achDelay = () => Number(process.env.SETTLEMENT_DELAY_MS ?? '500');
const wireDelay = () => Number(process.env.WIRE_SETTLEMENT_DELAY_MS ?? '400');
const swiftDelay = () => Number(process.env.SWIFT_SETTLEMENT_DELAY_MS ?? '600');
const fednowDelay = () => Number(process.env.FEDNOW_SETTLEMENT_DELAY_MS ?? '50');
const checkMailDelay = () => Number(process.env.CHECK_MAIL_DELAY_MS ?? '800');
const checkClearDelay = () => Number(process.env.CHECK_CLEAR_DELAY_MS ?? '1200');

export function getTransferOrThrow(id: string): TransferRecord {
  const t = getTransfer(id);
  if (!t) {
    throw new Error('transfer_not_found');
  }
  return t;
}

function save(t: TransferRecord): void {
  putTransfer(t);
}

export function processAchTransfer(input: {
  direction: AchDirection;
  account_id: string;
  amount_cents: number;
  currency?: string;
  idempotency_key?: string;
  sec_code?: string;
  metadata?: Record<string, unknown>;
}): { transfer: TransferRecord; duplicate: boolean } {
  const acc = ensureAccountWithId(input.account_id, input.currency ?? 'USD');
  if (input.amount_cents <= 0) {
    throw new Error('invalid_amount');
  }
  const currency = input.currency ?? acc.currency;
  if (input.idempotency_key) {
    const existingId = findIdempotency(input.idempotency_key);
    if (existingId) {
      const existing = getTransfer(existingId);
      if (existing) {
        return { transfer: existing, duplicate: true };
      }
    }
  }
  const isDebit = input.direction === 'debit';
  if (isDebit && acc.balanceCents < input.amount_cents) {
    throw new Error('insufficient_funds');
  }
  const id = randomTransferId();
  const delta = isDebit ? -input.amount_cents : input.amount_cents;
  applyBalanceDelta(input.account_id, delta);
  const t: TransferRecord = {
    id,
    rail: 'ach',
    status: 'pending',
    amountCents: input.amount_cents,
    currency,
    createdAt: nowIso(),
    accountId: input.account_id,
    achDirection: input.direction,
    secCode: input.sec_code,
    nachaMetadata: input.metadata,
    idempotencyKey: input.idempotency_key,
  };
  save(t);
  if (input.idempotency_key) {
    registerIdempotency(input.idempotency_key, id);
  }
  emit('transfer.ach.submitted', {
    rail: 'ach',
    transfer_id: id,
    account_id: input.account_id,
    amount_cents: input.amount_cents,
    direction: input.direction,
  });
  setTimeout(() => runAchSettlement(id), achDelay());
  return { transfer: t, duplicate: false };
}

export function runAchSettlement(id: string, options?: { forceSuccess?: boolean }): void {
  const t = getTransfer(id);
  if (!t || t.rail !== 'ach' || t.status !== 'pending') {
    return;
  }
  let shouldFail = getFailNextAch();
  if (shouldFail) {
    setFailNextAch(false);
  }
  if (options?.forceSuccess) {
    shouldFail = false;
  }
  const isDebit = t.achDirection === 'debit';
  const delta = isDebit ? -t.amountCents : t.amountCents;
  if (shouldFail) {
    applyBalanceDelta(t.accountId!, -delta);
    t.status = 'failed';
    t.settledAt = nowIso();
    save(t);
    emit('transfer.ach.failed', {
      rail: 'ach',
      transfer_id: id,
      account_id: t.accountId,
      amount_cents: t.amountCents,
      reason: 'simulated_failure',
    });
    return;
  }
  t.status = 'settled';
  t.settledAt = nowIso();
  save(t);
  emit('transfer.ach.settled', {
    rail: 'ach',
    transfer_id: id,
    account_id: t.accountId,
    amount_cents: t.amountCents,
    direction: t.achDirection,
  });
}

export function runAchReturn(
  id: string,
): { ok: true } | { ok: false; error: string } {
  const t = getTransfer(id);
  if (!t || t.rail !== 'ach') {
    return { ok: false, error: 'transfer_not_found' };
  }
  if (t.status !== 'settled') {
    return { ok: false, error: 'transfer_not_settled' };
  }
  const isDebit = t.achDirection === 'debit';
  const delta = isDebit ? -t.amountCents : t.amountCents;
  applyBalanceDelta(t.accountId!, -delta);
  t.status = 'returned';
  save(t);
  emit('transfer.ach.returned', {
    rail: 'ach',
    transfer_id: id,
    account_id: t.accountId,
    amount_cents: t.amountCents,
    direction: t.achDirection,
  });
  return { ok: true };
}

export function scheduleAchSettlement(id: string): void {
  setTimeout(() => runAchSettlement(id), achDelay());
}

/** Legacy alias: push = credit, pull = debit */
export function processLegacyAchPushPull(
  kind: 'push' | 'pull',
  input: { account_id: string; amount_cents: number; idempotency_key?: string },
): { transfer: TransferRecord; duplicate: boolean } {
  const direction: AchDirection = kind === 'push' ? 'credit' : 'debit';
  return processAchTransfer({
    direction,
    account_id: input.account_id,
    amount_cents: input.amount_cents,
    idempotency_key: input.idempotency_key,
  });
}

export function processWireTransfer(input: {
  account_id: string;
  amount_cents: number;
  currency?: string;
  idempotency_key?: string;
  subtype?: 'outgoing' | 'drawdown';
  beneficiary?: { routing_number?: string; account_number?: string; name?: string };
}): { transfer: TransferRecord; duplicate: boolean } {
  const acc = ensureAccountWithId(input.account_id, input.currency ?? 'USD');
  if (input.amount_cents <= 0) {
    throw new Error('invalid_amount');
  }
  if (input.idempotency_key) {
    const existingId = findIdempotency(input.idempotency_key);
    if (existingId) {
      const existing = getTransfer(existingId);
      if (existing) {
        return { transfer: existing, duplicate: true };
      }
    }
  }
  const subtype = input.subtype ?? 'outgoing';
  if (subtype === 'outgoing' && acc.balanceCents < input.amount_cents) {
    throw new Error('insufficient_funds');
  }
  const id = randomTransferId();
  const currency = input.currency ?? acc.currency;
  if (subtype === 'outgoing') {
    applyBalanceDelta(input.account_id, -input.amount_cents);
  } else {
    applyBalanceDelta(input.account_id, input.amount_cents);
  }
  const t: TransferRecord = {
    id,
    rail: 'wire',
    status: 'processing',
    amountCents: input.amount_cents,
    currency,
    createdAt: nowIso(),
    accountId: input.account_id,
    wireSubtype: subtype,
    beneficiary: input.beneficiary,
    idempotencyKey: input.idempotency_key,
  };
  save(t);
  if (input.idempotency_key) {
    registerIdempotency(input.idempotency_key, id);
  }
  emit('transfer.wire.sent', {
    rail: 'wire',
    transfer_id: id,
    account_id: input.account_id,
    amount_cents: input.amount_cents,
    subtype,
    counterparty: input.beneficiary ?? {},
  });
  setTimeout(() => finalizeWire(id), wireDelay());
  return { transfer: t, duplicate: false };
}

function finalizeWire(id: string): void {
  const t = getTransfer(id);
  if (!t || t.rail !== 'wire' || t.status !== 'processing') {
    return;
  }
  let fail = getFailNextWire();
  if (fail) {
    setFailNextWire(false);
  }
  if (fail) {
    if (t.wireSubtype === 'outgoing') {
      applyBalanceDelta(t.accountId!, t.amountCents);
    } else {
      applyBalanceDelta(t.accountId!, -t.amountCents);
    }
    t.status = 'failed';
    t.settledAt = nowIso();
    save(t);
    emit('transfer.wire.failed', {
      rail: 'wire',
      transfer_id: id,
      account_id: t.accountId,
      reason: 'simulated_failure',
    });
    return;
  }
  t.status = 'settled';
  t.settledAt = nowIso();
  save(t);
  emit('transfer.wire.settled', {
    rail: 'wire',
    transfer_id: id,
    account_id: t.accountId,
    amount_cents: t.amountCents,
  });
}

export function processSwiftTransfer(input: {
  account_id: string;
  amount_cents: number;
  currency: string;
  bic: string;
  idempotency_key?: string;
  beneficiary?: Record<string, unknown>;
}): { transfer: TransferRecord; duplicate: boolean } {
  const acc = ensureAccountWithId(input.account_id, input.currency);
  if (input.amount_cents <= 0) {
    throw new Error('invalid_amount');
  }
  if (input.idempotency_key) {
    const existingId = findIdempotency(input.idempotency_key);
    if (existingId) {
      const existing = getTransfer(existingId);
      if (existing) {
        return { transfer: existing, duplicate: true };
      }
    }
  }
  if (acc.balanceCents < input.amount_cents) {
    throw new Error('insufficient_funds');
  }
  const id = randomTransferId();
  applyBalanceDelta(input.account_id, -input.amount_cents);
  const t: TransferRecord = {
    id,
    rail: 'swift',
    status: 'processing',
    amountCents: input.amount_cents,
    currency: input.currency,
    createdAt: nowIso(),
    accountId: input.account_id,
    bic: input.bic,
    swiftBeneficiary: input.beneficiary,
    idempotencyKey: input.idempotency_key,
  };
  save(t);
  if (input.idempotency_key) {
    registerIdempotency(input.idempotency_key, id);
  }
  emit('transfer.swift.submitted', {
    rail: 'swift',
    transfer_id: id,
    account_id: input.account_id,
    amount_cents: input.amount_cents,
    bic: input.bic,
  });
  setTimeout(() => finalizeSwift(id), swiftDelay());
  return { transfer: t, duplicate: false };
}

function finalizeSwift(id: string): void {
  const t = getTransfer(id);
  if (!t || t.rail !== 'swift' || t.status !== 'processing') {
    return;
  }
  t.status = 'settled';
  t.settledAt = nowIso();
  save(t);
  emit('transfer.swift.completed', {
    rail: 'swift',
    transfer_id: id,
    account_id: t.accountId,
    amount_cents: t.amountCents,
  });
}

export function processFednowTransfer(input: {
  account_id: string;
  amount_cents: number;
  currency?: string;
  direction: 'send' | 'receive';
  idempotency_key?: string;
  counterparty?: Record<string, unknown>;
}): { transfer: TransferRecord; duplicate: boolean } {
  const acc = ensureAccountWithId(input.account_id, input.currency ?? 'USD');
  if (input.amount_cents <= 0) {
    throw new Error('invalid_amount');
  }
  if (input.idempotency_key) {
    const existingId = findIdempotency(input.idempotency_key);
    if (existingId) {
      const existing = getTransfer(existingId);
      if (existing) {
        return { transfer: existing, duplicate: true };
      }
    }
  }
  const currency = input.currency ?? acc.currency;
  if (input.direction === 'send' && acc.balanceCents < input.amount_cents) {
    throw new Error('insufficient_funds');
  }
  const id = randomTransferId();
  const delta = input.direction === 'send' ? -input.amount_cents : input.amount_cents;
  applyBalanceDelta(input.account_id, delta);
  const t: TransferRecord = {
    id,
    rail: 'fednow',
    status: 'pending',
    amountCents: input.amount_cents,
    currency,
    createdAt: nowIso(),
    accountId: input.account_id,
    counterparty: input.counterparty,
    idempotencyKey: input.idempotency_key,
  };
  save(t);
  if (input.idempotency_key) {
    registerIdempotency(input.idempotency_key, id);
  }
  setTimeout(() => finalizeFednow(id), fednowDelay());
  return { transfer: t, duplicate: false };
}

function finalizeFednow(id: string): void {
  const t = getTransfer(id);
  if (!t || t.rail !== 'fednow' || t.status !== 'pending') {
    return;
  }
  const direction = t.fednowDirection ?? 'send';
  t.status = 'settled';
  t.settledAt = nowIso();
  save(t);
  emit('transfer.fednow.settled', {
    rail: 'fednow',
    transfer_id: id,
    account_id: t.accountId,
    amount_cents: t.amountCents,
    direction,
  });
}

export function processBookTransfer(input: {
  from_account_id: string;
  to_account_id: string;
  amount_cents: number;
  idempotency_key?: string;
}): { transfer: TransferRecord; duplicate: boolean } {
  const from = ensureAccountWithId(input.from_account_id);
  const to = ensureAccountWithId(input.to_account_id);
  if (from.currency !== to.currency) {
    throw new Error('currency_mismatch');
  }
  if (input.amount_cents <= 0) {
    throw new Error('invalid_amount');
  }
  if (input.idempotency_key) {
    const existingId = findIdempotency(input.idempotency_key);
    if (existingId) {
      const existing = getTransfer(existingId);
      if (existing) {
        return { transfer: existing, duplicate: true };
      }
    }
  }
  if (from.balanceCents < input.amount_cents) {
    throw new Error('insufficient_funds');
  }
  const id = randomTransferId();
  applyBalanceDelta(input.from_account_id, -input.amount_cents);
  applyBalanceDelta(input.to_account_id, input.amount_cents);
  const t: TransferRecord = {
    id,
    rail: 'book',
    status: 'settled',
    amountCents: input.amount_cents,
    currency: from.currency,
    createdAt: nowIso(),
    settledAt: nowIso(),
    fromAccountId: input.from_account_id,
    toAccountId: input.to_account_id,
    idempotencyKey: input.idempotency_key,
  };
  save(t);
  if (input.idempotency_key) {
    registerIdempotency(input.idempotency_key, id);
  }
  emit('transfer.book.completed', {
    rail: 'book',
    transfer_id: id,
    from_account_id: input.from_account_id,
    to_account_id: input.to_account_id,
    amount_cents: input.amount_cents,
  });
  return { transfer: t, duplicate: false };
}

export function processCheckTransfer(input: {
  account_id: string;
  amount_cents: number;
  payee: string;
  memo?: string;
  action?: 'issue' | 'stop' | 'return';
  idempotency_key?: string;
}): { transfer: TransferRecord; duplicate: boolean } {
  const acc = ensureAccountWithId(input.account_id);
  if (input.amount_cents <= 0) {
    throw new Error('invalid_amount');
  }
  const action = input.action ?? 'issue';
  if (action === 'issue') {
    if (acc.balanceCents < input.amount_cents) {
      throw new Error('insufficient_funds');
    }
    if (input.idempotency_key) {
      const existingId = findIdempotency(input.idempotency_key);
      if (existingId) {
        const existing = getTransfer(existingId);
        if (existing) {
          return { transfer: existing, duplicate: true };
        }
      }
    }
    const id = randomTransferId();
    applyBalanceDelta(input.account_id, -input.amount_cents);
    const t: TransferRecord = {
      id,
      rail: 'check',
      status: 'pending',
      amountCents: input.amount_cents,
      currency: acc.currency,
      createdAt: nowIso(),
      accountId: input.account_id,
      checkPayee: input.payee,
      checkMemo: input.memo,
      checkAction: 'issue',
      idempotencyKey: input.idempotency_key,
    };
    save(t);
    if (input.idempotency_key) {
      registerIdempotency(input.idempotency_key, id);
    }
    emit('transfer.check.issued', {
      rail: 'check',
      transfer_id: id,
      account_id: input.account_id,
      amount_cents: input.amount_cents,
      payee: input.payee,
    });
    setTimeout(() => mailCheck(id), checkMailDelay());
    return { transfer: t, duplicate: false };
  }
  if (action === 'stop' || action === 'return') {
    throw new Error('use_transfer_id');
  }
  throw new Error('invalid_action');
}

function mailCheck(id: string): void {
  const t = getTransfer(id);
  if (!t || t.rail !== 'check' || t.status !== 'pending') {
    return;
  }
  t.status = 'processing';
  save(t);
  emit('transfer.check.mailed', {
    rail: 'check',
    transfer_id: id,
    account_id: t.accountId,
  });
  setTimeout(() => clearCheck(id), checkClearDelay());
}

function clearCheck(id: string): void {
  const t = getTransfer(id);
  if (!t || t.rail !== 'check' || t.status !== 'processing') {
    return;
  }
  let fail = getFailNextCheckClear();
  if (fail) {
    setFailNextCheckClear(false);
  }
  if (fail) {
    applyBalanceDelta(t.accountId!, t.amountCents);
    t.status = 'returned';
    t.settledAt = nowIso();
    save(t);
    emit('transfer.check.returned', {
      rail: 'check',
      transfer_id: id,
      account_id: t.accountId,
      reason: 'simulated_return',
    });
    return;
  }
  t.status = 'settled';
  t.settledAt = nowIso();
  save(t);
  emit('transfer.check.cleared', {
    rail: 'check',
    transfer_id: id,
    account_id: t.accountId,
    amount_cents: t.amountCents,
  });
}

export function processCheckActionByTransferId(
  transferId: string,
  action: 'stop' | 'return',
): TransferRecord {
  const t = getTransfer(transferId);
  if (!t || t.rail !== 'check') {
    throw new Error('transfer_not_found');
  }
  if (action === 'stop' && (t.status === 'pending' || t.status === 'processing')) {
    applyBalanceDelta(t.accountId!, t.amountCents);
    t.status = 'cancelled';
    t.settledAt = nowIso();
    save(t);
    emit('transfer.check.stopped', {
      rail: 'check',
      transfer_id: transferId,
      account_id: t.accountId,
    });
    return t;
  }
  if (action === 'return' && t.status === 'settled') {
    applyBalanceDelta(t.accountId!, t.amountCents);
    t.status = 'returned';
    t.settledAt = nowIso();
    save(t);
    emit('transfer.check.returned', {
      rail: 'check',
      transfer_id: transferId,
      account_id: t.accountId,
      reason: 'return_after_clear',
    });
    return t;
  }
  throw new Error('invalid_check_action');
}

export function runAchSettlementPublic(id: string, options?: { forceSuccess?: boolean }): void {
  runAchSettlement(id, options);
}
