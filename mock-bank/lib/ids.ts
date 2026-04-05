import { randomUUID } from 'crypto';

export function randomRef(): string {
  return `tx_${randomUUID().replace(/-/g, '').slice(0, 16)}`;
}

export function randomTransferId(): string {
  return `trf_${randomUUID().replace(/-/g, '').slice(0, 16)}`;
}

export function randomKycId(): string {
  return `kyc_${randomUUID().replace(/-/g, '').slice(0, 16)}`;
}
