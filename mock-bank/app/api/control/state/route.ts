import { listKycSubmissions } from '@/lib/kyc/store';
import {
  getFailNextAch,
  getFailNextCheckClear,
  getFailNextWire,
  listAccounts,
  listTransfers,
} from '@/lib/store';

export async function GET() {
  const accounts = listAccounts().map((a) => ({
    id: a.id,
    currency: a.currency,
    balance_cents: a.balanceCents,
    created_at: a.createdAt,
  }));
  const transfers = listTransfers().map((t) => ({
    transfer_id: t.id,
    rail: t.rail,
    status: t.status,
    amount_cents: t.amountCents,
    account_id: t.accountId ?? null,
    from_account_id: t.fromAccountId ?? null,
    to_account_id: t.toAccountId ?? null,
    created_at: t.createdAt,
  }));
  const kyc_submissions = listKycSubmissions().map((k) => ({
    id: k.id,
    status: k.status,
    account_id: k.account_id ?? null,
    created_at: k.createdAt,
  }));
  return Response.json({
    fail_next_ach: getFailNextAch(),
    fail_next_wire: getFailNextWire(),
    fail_next_check_clear: getFailNextCheckClear(),
    accounts,
    transfers,
    kyc_submissions,
  });
}
