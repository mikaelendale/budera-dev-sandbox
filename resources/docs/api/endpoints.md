# Endpoints (v1)

All paths are prefixed with `/api/v1`. Authenticate with a Bearer token (API key or OAuth2 access token).

## Wallet

| Method | Path | Ability | Description |
|--------|------|---------|-------------|
| GET | `wallet/me` | `wallet:read` | Current wallet context for the authenticated key/token |
| POST | `wallet/accounts` | `wallet:pay` | Create a new wallet account |
| GET | `wallet/accounts/{walletAccount}` | `wallet:read` | Get wallet account details |
| POST | `wallet/accounts/{walletAccount}/kyc` | `wallet:pay` | Initiate KYC verification for a wallet |

> **Tip:** Wallet accounts belong to **end users**, not to your company. Each wallet gets a unique `public_id` (e.g. `wal_xxx`) used in all subsequent API calls.

## Payments

| Method | Path | Ability | Description |
|--------|------|---------|-------------|
| GET | `payments` | `wallet:read` | List payments. Optional `?wallet_account_id=` filter |
| POST | `payments` | `wallet:pay` | Create a payment. **Requires `Idempotency-Key`** |
| GET | `payments/{payment}` | `wallet:read` | Get payment details |

Payments pass through the **spend controls pipeline** before execution: policy limits → balance check → velocity engine → approval gate → compliance screen. If any gate rejects, the payment is held or denied.

## Top-ups

| Method | Path | Ability | Description |
|--------|------|---------|-------------|
| GET | `topups` | `wallet:read` | List top-ups |
| POST | `topups` | `wallet:topup` | Create a top-up (fund a wallet). **Requires `Idempotency-Key`** |
| GET | `topups/{topup}` | `wallet:read` | Get top-up details |

## Transfers

| Method | Path | Ability | Description |
|--------|------|---------|-------------|
| GET | `transfers` | `wallet:read` | List transfers |
| POST | `transfers` | `wallet:transfer` | Create a transfer between wallets. **Requires `Idempotency-Key`** |
| GET | `transfers/{transfer}` | `wallet:read` | Get transfer details |

## Ledger

| Method | Path | Ability | Description |
|--------|------|---------|-------------|
| GET | `wallets/{walletAccount}/ledger` | `wallet:read` | Get ledger entries for a wallet |

The ledger is a **double-entry** record of all money movement. Each entry includes type, amount, running balance, and a description.

## Bank links

| Method | Path | Ability | Description |
|--------|------|---------|-------------|
| POST | `bank-links` | `wallet:link` | Create a bank link (credentials or hosted session) |
| GET | `bank-links/{bankLink}` | `wallet:read` | Get bank link details |
| POST | `bank-links/{bankLink}/verify` | `wallet:link` | Verify via micro-deposits |
| DELETE | `bank-links/{bankLink}` | `wallet:link` | Remove a bank link |

> **Tip:** In sandbox mode, bank links use a **mock bank**. In production, they connect to real bank accounts via the partner bank integration.

## Approvals

| Method | Path | Ability | Description |
|--------|------|---------|-------------|
| POST | `approvals/{token}/approve` | `wallet:approve` | Approve a held payment |
| POST | `approvals/{token}/deny` | `wallet:approve` | Deny a held payment |

When a payment exceeds policy thresholds, it enters a `held_for_approval` state. The end user (or an authorized party) can approve or deny it using the token.

## Sandbox simulation

These endpoints are **sandbox-only** and require a sandbox API key with `sandbox:simulate` ability.

| Method | Path | Description |
|--------|------|-------------|
| POST | `sandbox/simulate/settlement` | Simulate ACH settlement for a payment |
| POST | `sandbox/simulate/return` | Simulate an ACH return |
| POST | `sandbox/simulate/kyc-approve` | Simulate KYC approval for a wallet |
| POST | `sandbox/simulate/microdeposit` | Simulate micro-deposit arrival for bank link verification |
| POST | `sandbox/kyc/approve/{walletKycVerification}` | Direct KYC approval (dev-only) |

> **Tip:** Use these to test your full integration flow without waiting for real bank processing. Settlements typically take 1-3 business days in production.

## Inbound webhook (unversioned)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/webhooks/mock-bank` | Inbound webhook from the mock bank service |

This endpoint uses **HMAC-SHA256** verification on the raw request body with `X-Signature: sha256=<hex>`. Not authenticated via API key.
