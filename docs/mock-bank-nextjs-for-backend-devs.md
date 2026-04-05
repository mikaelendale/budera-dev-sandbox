# Mock bank for backend developers (no React required)

You do **not** need to learn React to use or extend the mock. The product-facing surface is **HTTP**: JSON in, JSON out. The Next.js app is only the runtime that hosts route handlers.

## What is Next.js doing here?

- **App Router:** URLs map to folders under `mock-bank/app/`.
- **Route handlers:** A file named `route.ts` that exports `GET`, `POST`, etc. is a **server-only HTTP endpoint**. Example: `app/api/transfers/ach/route.ts` handles `POST /api/transfers/ach`.
- **No browser required:** `npm run dev` starts a Node server on port 3000. You can drive everything with curl, Postman, or Laravel `MockBankClient`.

## Folder map

| Path | Role |
|------|------|
| `app/**/route.ts` | HTTP endpoints (API) |
| `app/control/page.tsx` | Optional debug UI (can ignore) |
| `lib/store.ts` | In-memory accounts |
| `lib/transfers/engine.ts` | Transfer logic + webhooks |
| `lib/kyc/` | KYC simulator |
| `lib/webhook.ts` | Sign outbound webhooks with HMAC-SHA256 |

## Environment

Copy `mock-bank/.env.example` to `.env.local`. Key ideas:

- **`MOCK_BANK_SECRET`:** If set, send header `X-Bank-Secret: <value>` on every bank API call (Laravel does this via `MockBankClient`).
- **`BUDERA_WEBHOOK_URL`:** Where the mock POSTs events (your Laravel `/api/webhooks/mock-bank`).
- **`BUDERA_WEBHOOK_SECRET`:** Must equal Laravel `MOCK_BANK_WEBHOOK_SECRET`. The mock sends `X-Signature: sha256=<hex>` where hex is HMAC-SHA256 of the **raw JSON body**.

## Commands

```bash
npm install
npm run dev     # development server :3000
npm run build && npm start   # production
npm test        # Vitest
```

## curl examples

Health (no secret):

```bash
curl -s http://127.0.0.1:3000/health
```

ACH (with secret):

```bash
curl -s -X POST http://127.0.0.1:3000/api/transfers/ach \
  -H "Content-Type: application/json" \
  -H "X-Bank-Secret: your-secret" \
  -d "{\"direction\":\"credit\",\"account_id\":\"acct_xxx\",\"amount_cents\":1000}"
```

Book transfer:

```bash
curl -s -X POST http://127.0.0.1:3000/api/transfers/book \
  -H "Content-Type: application/json" \
  -H "X-Bank-Secret: your-secret" \
  -d "{\"from_account_id\":\"acct_a\",\"to_account_id\":\"acct_b\",\"amount_cents\":500}"
```

KYC:

```bash
curl -s -X POST http://127.0.0.1:3000/api/kyc/submissions \
  -H "Content-Type: application/json" \
  -H "X-Bank-Secret: your-secret" \
  -d "{\"account_id\":\"acct_xxx\",\"legal_name\":\"Jane Doe\",\"last4_ssn\":\"1234\"}"
```

## Laravel side

Use [`app/Services/Banking/MockBankClient.php`](../app/Services/Banking/MockBankClient.php) instead of hand-written curl. Configure `MOCK_BANK_BASE_URL` and optional `MOCK_BANK_SECRET` in Laravel `.env`.
