import { processLegacyAchPushPull, runAchSettlementPublic } from './transfers/engine';

export function processAch(input: {
  account_id: string;
  amount_cents: number;
  idempotency_key?: string;
  kind: 'push' | 'pull';
}) {
  return processLegacyAchPushPull(input.kind, {
    account_id: input.account_id,
    amount_cents: input.amount_cents,
    idempotency_key: input.idempotency_key,
  });
}

export function runSettlement(ref: string, options?: { forceSuccess?: boolean }): void {
  runAchSettlementPublic(ref, options);
}
