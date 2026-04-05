# Column mock bank

Sandbox HTTP API that mimics a **Column-style** bank for local Budera development (timeline §05). Code lives in [`mock-bank/`](../mock-bank/): Next.js App Router **route handlers**, in-memory ledger, simulated rails, and outbound webhooks to Laravel.

## What it includes

| Feature | Description |
|--------|-------------|
| **Unified transfers** | `POST /api/transfers/*` for ACH, wire, SWIFT, FedNow/realtime, book, check |
| **Legacy ACH** | `POST /api/ach/push` and `/api/ach/pull` delegate to the same ACH engine (`trf_*` ids) |
| **KYC simulator** | `POST /api/kyc/submissions`, `GET /api/kyc/submissions/:id` |
| **Auth** | Optional `MOCK_BANK_SECRET` on bank APIs (`X-Bank-Secret` or `Authorization: Bearer`) |
| **Delays** | Env-tunable delays per rail (ACH, wire, check mail/clear, KYC) |
| **Webhooks** | `POST` to `BUDERA_WEBHOOK_URL` with `X-Signature: sha256=<hmac>` |
| **Control UI** | [`/control`](http://localhost:3000/control) — accounts, transfers, KYC list, fail-next toggles, ACH settle-now |
| **In-memory** | Resets on process restart |

## HTTP surface (mock)

| Method | Path | Notes |
|--------|------|--------|
| `GET` | `/health` | Ping |
| `POST` | `/api/accounts` | Create account → `account.created` |
| `GET` | `/api/accounts/:id/balance` | Balance |
| `POST` | `/api/transfers/ach` | Body: `direction` (`credit`/`debit` or `push`/`pull`), `account_id`, `amount_cents`, optional `sec_code`, `metadata`, `idempotency_key` |
| `POST` | `/api/transfers/wire` | `account_id`, `amount_cents`, optional `subtype` (`outgoing`/`drawdown`), `beneficiary` |
| `POST` | `/api/transfers/swift` | `account_id`, `amount_cents`, `currency`, `bic`, optional `beneficiary` |
| `POST` | `/api/transfers/fednow` | `account_id`, `amount_cents`, `direction` (`send`/`receive`), optional `counterparty` |
| `POST` | `/api/transfers/realtime` | Same as FedNow |
| `POST` | `/api/transfers/book` | `from_account_id`, `to_account_id`, `amount_cents` |
| `POST` | `/api/transfers/check` | Issue: `account_id`, `amount_cents`, `payee`, optional `memo`. Stop/return: `action`, `transfer_id` |
| `GET` | `/api/transfers/:id` | Unified transfer status |
| `GET` | `/api/transactions/:ref` | Same as transfer GET (compat) |
| `POST` | `/api/ach/push`, `/api/ach/pull` | Legacy; returns `ref` + `transfer_id` |
| `POST` | `/api/kyc/submissions` | Stub identity fields → `kyc.submitted`, then `kyc.verified` or `kyc.rejected` |
| `GET` | `/api/kyc/submissions/:id` | KYC status |
| `GET` | `/api/control/state` | No bank secret (demo panel data) |
| `POST` | `/api/control/fail-next` | Body `{ "enabled": boolean, "target"?: "ach" \| "wire" \| "check_clear" }` |
| `POST` | `/api/control/settle-now` | Body `{ "ref": "trf_..." }` — pending ACH only, forces success |

## Webhook events (outbound)

Namespaced examples: `transfer.ach.submitted`, `transfer.ach.settled`, `transfer.ach.failed`, `transfer.wire.sent`, `transfer.wire.settled`, `transfer.wire.failed`, `transfer.swift.submitted`, `transfer.swift.completed`, `transfer.fednow.settled`, `transfer.book.completed`, `transfer.check.issued`, `transfer.check.mailed`, `transfer.check.cleared`, `transfer.check.returned`, `transfer.check.stopped`, `account.created`, `kyc.submitted`, `kyc.verified`, `kyc.rejected`.

Payload `data` includes `rail`, `transfer_id` (or `kyc_submission_id` for KYC) where applicable.

## Mock environment (`mock-bank/.env.example`)

| Variable | Purpose |
|----------|---------|
| `MOCK_BANK_SECRET` | If set, required on protected bank APIs |
| `SETTLEMENT_DELAY_MS` | ACH settlement delay |
| `WIRE_SETTLEMENT_DELAY_MS` | Wire simulate |
| `SWIFT_SETTLEMENT_DELAY_MS` | SWIFT simulate |
| `FEDNOW_SETTLEMENT_DELAY_MS` | FedNow simulate |
| `CHECK_MAIL_DELAY_MS` | Check “mailed” step |
| `CHECK_CLEAR_DELAY_MS` | Check “cleared” step |
| `KYC_VERIFICATION_DELAY_MS` | Time before verified/rejected |
| `KYC_MOCK_REJECT` | `true` / `1` to always reject KYC |
| `BUDERA_WEBHOOK_URL` | Laravel webhook URL |
| `BUDERA_WEBHOOK_SECRET` | Shared with Laravel `MOCK_BANK_WEBHOOK_SECRET` |

## Laravel wiring

| Variable | Purpose |
|----------|---------|
| `MOCK_BANK_BASE_URL` | Mock base URL |
| `MOCK_BANK_SECRET` | Matches mock when auth enabled |
| `MOCK_BANK_WEBHOOK_SECRET` | Verifies inbound webhooks |

### Code

- [`config/services.php`](../config/services.php) — `mock_bank`
- [`app/Services/Banking/MockBankClient.php`](../app/Services/Banking/MockBankClient.php) — HTTP client (transfers + KYC)
- [`app/Services/Banking/WalletProvisioningService.php`](../app/Services/Banking/WalletProvisioningService.php) — create wallet + partner account + KYC row
- [`app/Http/Controllers/Api/MockBankWebhookController.php`](../app/Http/Controllers/Api/MockBankWebhookController.php) — HMAC verify, persist [`bank_webhook_events`](../database/migrations/2026_03_22_170000_create_bank_webhook_events_table.php), update KYC rows on `kyc.verified` / `kyc.rejected`
- API: `POST /api/v1/wallet/accounts` (`wallet:pay`), `GET /api/v1/wallet/accounts/{id}` (`wallet:read`), `POST /api/v1/wallet/accounts/{id}/kyc` (`wallet:pay`)

### Local webhook URL

Example: `http://127.0.0.1:8000/api/webhooks/mock-bank` when Laravel is `php artisan serve` on port 8000. The mock must reach this URL from its process (same machine is fine).

## Run locally

```bash
cd mock-bank
cp .env.example .env.local
npm install
npm run dev
npm test   # Vitest unit tests
```

```bash
php artisan bank:ping
```

## Tests

- **mock-bank:** `npm test` (Vitest) — store + signing helpers
- **Laravel:** `tests/Feature/MockBank*.php` — client, webhooks, wallet API

## Deployment

Use a **long-lived Node process** for reliable `setTimeout` settlement. Serverless is a poor fit for delayed jobs and in-memory state.

## Troubleshooting

**`ChunkLoadError` / 404 on `/_next/static/chunks/...` (e.g. on `/control`):** The tab is loading an **old HTML** that points at **chunk files from a previous build**. Fix: stop the dev server, run `npm run clean` in `mock-bank/`, start again with `npm run dev`, then hard-refresh the browser (Ctrl+Shift+R) or use a private window. Do not mix `npm run dev` and `npm run start` in the same browser session without a full refresh.

**404 on `GET /api/accounts/:id/balance` right after creating the account:** In dev, Next.js could load more than one copy of the in-memory store module. The mock keeps ledger state on `globalThis` so all routes share one store. Restart `npm run dev` after pulling changes if you still see mismatches.

## Backend dev primer (Next.js)

See [`docs/mock-bank-nextjs-for-backend-devs.md`](mock-bank-nextjs-for-backend-devs.md).
