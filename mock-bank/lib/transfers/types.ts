export type TransferRail = 'ach' | 'wire' | 'swift' | 'fednow' | 'book' | 'check';

export type TransferStatus =
  | 'pending'
  | 'processing'
  | 'settled'
  | 'failed'
  | 'returned'
  | 'cancelled';

export type AchDirection = 'credit' | 'debit';

export type TransferRecord = {
  id: string;
  rail: TransferRail;
  status: TransferStatus;
  amountCents: number;
  currency: string;
  createdAt: string;
  settledAt?: string;
  idempotencyKey?: string;
  accountId?: string;
  fromAccountId?: string;
  toAccountId?: string;
  achDirection?: AchDirection;
  secCode?: string;
  nachaMetadata?: Record<string, unknown>;
  wireSubtype?: 'outgoing' | 'drawdown';
  beneficiary?: {
    routing_number?: string;
    account_number?: string;
    name?: string;
  };
  bic?: string;
  swiftBeneficiary?: Record<string, unknown>;
  checkPayee?: string;
  checkMemo?: string;
  checkAction?: 'issue' | 'stop' | 'return';
  counterparty?: Record<string, unknown>;
  fednowDirection?: 'send' | 'receive';
};
