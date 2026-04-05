import { sendBuderaWebhook } from '@/lib/webhook';

import { getKycSubmission, updateKycStatus, type KycSubmission } from './store';

const kycDelay = () => Number(process.env.KYC_VERIFICATION_DELAY_MS ?? '800');

export function scheduleKycResolution(sub: KycSubmission): void {
  setTimeout(() => {
    const row = getKycSubmission(sub.id);
    if (!row || row.status !== 'pending') {
      return;
    }
    const reject = process.env.KYC_MOCK_REJECT === '1' || process.env.KYC_MOCK_REJECT === 'true';
    if (reject) {
      updateKycStatus(row.id, 'rejected');
      void sendBuderaWebhook({
        event: 'kyc.rejected',
        id: `evt_kyc_${row.id}`,
        occurred_at: new Date().toISOString(),
        data: {
          kyc_submission_id: row.id,
          account_id: row.account_id ?? null,
          reason: 'simulated_reject',
        },
      });
      return;
    }
    updateKycStatus(row.id, 'verified');
    void sendBuderaWebhook({
      event: 'kyc.verified',
      id: `evt_kyc_${row.id}`,
      occurred_at: new Date().toISOString(),
      data: {
        kyc_submission_id: row.id,
        account_id: row.account_id ?? null,
      },
    });
  }, kycDelay());
}

export function emitKycSubmitted(sub: KycSubmission): void {
  void sendBuderaWebhook({
    event: 'kyc.submitted',
    id: `evt_kyc_${sub.id}`,
    occurred_at: new Date().toISOString(),
    data: {
      kyc_submission_id: sub.id,
      account_id: sub.account_id ?? null,
    },
  });
}
