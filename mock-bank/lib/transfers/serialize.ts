import type { TransferRecord } from './types';

export function transferToJson(t: TransferRecord): Record<string, unknown> {
  return {
    transfer_id: t.id,
    ref: t.id,
    rail: t.rail,
    status: t.status,
    amount_cents: t.amountCents,
    currency: t.currency,
    created_at: t.createdAt,
    settled_at: t.settledAt ?? null,
    account_id: t.accountId ?? null,
    from_account_id: t.fromAccountId ?? null,
    to_account_id: t.toAccountId ?? null,
    ach_direction: t.achDirection ?? null,
    sec_code: t.secCode ?? null,
    nacha_metadata: t.nachaMetadata ?? null,
    wire_subtype: t.wireSubtype ?? null,
    beneficiary: t.beneficiary ?? null,
    bic: t.bic ?? null,
    swift_beneficiary: t.swiftBeneficiary ?? null,
    check_payee: t.checkPayee ?? null,
    check_memo: t.checkMemo ?? null,
    check_action: t.checkAction ?? null,
    fednow_direction: t.fednowDirection ?? null,
    counterparty: t.counterparty ?? null,
  };
}
