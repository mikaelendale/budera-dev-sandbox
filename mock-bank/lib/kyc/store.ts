import { randomKycId } from '@/lib/ids';

export type KycStatus = 'pending' | 'verified' | 'rejected';

export type KycSubmission = {
  id: string;
  status: KycStatus;
  createdAt: string;
  resolvedAt?: string;
  account_id?: string;
  payload: {
    legal_name?: string;
    date_of_birth?: string;
    address_line1?: string;
    last4_ssn?: string;
  };
};

const KYC_KEY = '__buderaMockBankKycV1';

function submissionMap(): Map<string, KycSubmission> {
  const g = globalThis as unknown as Record<string, Map<string, KycSubmission>>;
  if (!g[KYC_KEY]) {
    g[KYC_KEY] = new Map();
  }
  return g[KYC_KEY];
}

export function createKycSubmission(input: {
  account_id?: string;
  legal_name?: string;
  date_of_birth?: string;
  address_line1?: string;
  last4_ssn?: string;
}): KycSubmission {
  const submissions = submissionMap();
  const id = randomKycId();
  const row: KycSubmission = {
    id,
    status: 'pending',
    createdAt: new Date().toISOString(),
    account_id: input.account_id,
    payload: {
      legal_name: input.legal_name,
      date_of_birth: input.date_of_birth,
      address_line1: input.address_line1,
      last4_ssn: input.last4_ssn,
    },
  };
  submissions.set(id, row);
  return row;
}

export function getKycSubmission(id: string): KycSubmission | undefined {
  return submissionMap().get(id);
}

export function listKycSubmissions(): KycSubmission[] {
  return [...submissionMap().values()];
}

export function updateKycStatus(id: string, status: KycStatus, resolvedAt?: string): void {
  const row = submissionMap().get(id);
  if (!row) {
    return;
  }
  row.status = status;
  row.resolvedAt = resolvedAt ?? new Date().toISOString();
}
