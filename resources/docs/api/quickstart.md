# Quickstart

Get up and running with the Budera API in minutes.

## 1. Create a company account

Sign up at the Budera dashboard and complete onboarding. Your company is your organization — it holds API keys, OAuth clients, webhook endpoints, and policies.

## 2. Create a sandbox API key

Go to **Company → API Keys** and create a new key with the abilities you need:

- `wallet:read` — read wallet, payment, and ledger data
- `wallet:pay` — create payments
- `wallet:link` — manage bank links
- `wallet:topup` — fund wallets
- `wallet:transfer` — move funds between wallets
- `sandbox:simulate` — access sandbox simulation endpoints

> **Tip:** Copy the key immediately — it's only shown once. Store it in an environment variable like `BUDERA_API_KEY`.

## 3. Verify your connection

```bash
curl -H "Authorization: Bearer $BUDERA_API_KEY" \
     https://app.budera.com/api/v1/wallet/me
```

A successful response confirms your key is valid and returns the company context.

## 4. Create a wallet for an end user

When an end user authorizes your AI agent (via OAuth), Budera creates a wallet. In sandbox mode, you can also create wallets via the API:

```bash
curl -X POST \
  -H "Authorization: Bearer $BUDERA_API_KEY" \
  -H "Content-Type: application/json" \
  https://app.budera.com/api/v1/wallet/accounts
```

## 5. Make a payment

```bash
curl -X POST \
  -H "Authorization: Bearer $BUDERA_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"wallet_account_id": "wal_xxx", "amount_cents": 1500, "description": "Coffee subscription"}' \
  https://app.budera.com/api/v1/payments
```

> **Important:** Always send an `Idempotency-Key` header on mutating requests (payments, transfers, top-ups). Use a unique UUID per logical operation. See the [Idempotency](/docs/idempotency) page.

## 6. Test with sandbox simulations

Sandbox-only endpoints let you simulate bank events without waiting for real ACH processing:

```bash
# Simulate a settlement
curl -X POST \
  -H "Authorization: Bearer $BUDERA_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"payment_id": "pay_xxx"}' \
  https://app.budera.com/api/v1/sandbox/simulate/settlement
```

Available simulations: `settlement`, `return`, `kyc-approve`, `microdeposit`.

> **Tip:** Simulation endpoints require a **sandbox** API key with the `sandbox:simulate` ability. They will reject live keys.

## 7. Set up webhooks

Go to **Company → Webhooks** and add an HTTPS endpoint. Budera will deliver signed events (e.g. `payment.approved`, `kyc.approved`) to your URL. See [Webhooks](/docs/webhooks) for HMAC verification details.

## Next steps

- Review the full [Endpoints](/docs/endpoints) reference
- Configure [spend controls and policies](/docs/security) for your agents
- When ready for production, submit for KYB review in **Company → General**
