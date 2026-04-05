# Budera API surface and account flows

This file is a **high-level map** of how an **AI company** integrates (API keys, OAuth) versus how a **human end user** gets a Budera account and joins your company. For full endpoint tables and request details, use the in-app docs at `**/docs`** (backed by `resources/docs/api/`), especially `**endpoints.md**` and `**authentication.md**`.

---

## Base URLs


| Surface               | Prefix                                | Notes                                                       |
| --------------------- | ------------------------------------- | ----------------------------------------------------------- |
| **REST API (v1)**     | `/api/v1/...`                         | Bearer auth: company **API key** or **OAuth2 access token** |
| **OAuth2 (Passport)** | `/oauth/authorize`, `/oauth/token`, … | Authorization code flow for user-delegated access           |
| **Web app (Inertia)** | `/`, `/dashboard`, `/company/...`, …  | Human sign-in, onboarding, company settings                 |


`APP_URL` in `.env` is the origin for all of the above (e.g. `http://localhost:8000` locally).

---

## Who calls what


| Actor                      | Typical access                                                                            | Role                                                                                             |
| -------------------------- | ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| **AI company (developer)** | Dashboard: API keys, OAuth apps, webhooks, policies                                       | Configures integration; keys are scoped to a **company** and **environment** (sandbox vs live).  |
| **Agent / backend job**    | `Authorization: Bearer <api_key_or_oauth_token>` on `/api/v1/*`                           | Moves money and reads state **according to key/token abilities**.                                |
| **End user (human)**       | Browser: register, onboarding, bank link / KYC sessions, payment approvals, OAuth consent | Owns **wallet accounts** under your company; authorizes agents via OAuth when you use that flow. |


---

## `/api/v1` endpoints (agent / server)

All routes use middleware `auth:api-key,api` and per-route **ability** checks (same names as OAuth scopes: `wallet:read`, `wallet:pay`, etc.).

**Wallet**

- `GET /api/v1/wallet/me` — `wallet:read`
- `POST /api/v1/wallet/accounts` — `wallet:pay` (provisions a wallet for the authenticated **user** context)
- `GET /api/v1/wallet/accounts/{walletAccount}` — `wallet:read`
- `POST /api/v1/wallet/accounts/{walletAccount}/kyc` — `wallet:pay`

**Payments, funding, transfers**

- `GET|POST /api/v1/payments`, `GET /api/v1/payments/{payment}` — read / create (`wallet:pay` + **Idempotency-Key** on POST)
- `GET|POST /api/v1/topups`, `GET /api/v1/topups/{topup}` — `wallet:topup` where applicable
- `GET|POST /api/v1/transfers`, `GET /api/v1/transfers/{transfer}` — `wallet:transfer` where applicable
- `GET /api/v1/wallets/{walletAccount}/ledger` — `wallet:read`

**Bank links**

- `POST /api/v1/bank-links`, `GET .../{bankLink}`, `POST .../verify`, `DELETE ...` — abilities `wallet:link` / `wallet:read` as enforced per route

**Approvals (held payments)**

- `POST /api/v1/approvals/{token}/approve` — `wallet:approve`
- `POST /api/v1/approvals/{token}/deny` — `wallet:approve`

**Sandbox-only** (sandbox API key + `sandbox:simulate`, plus environment checks)

- `POST /api/v1/sandbox/simulate/settlement`
- `POST /api/v1/sandbox/simulate/return`
- `POST /api/v1/sandbox/simulate/kyc-approve`
- `POST /api/v1/sandbox/simulate/microdeposit`
- `POST /api/v1/sandbox/kyc/approve/{walletKycVerification}` — additional middleware for sandbox KYC tooling

**Other**

- `POST /api/v1/webhooks/mock-bank` — mock bank webhook (throttled); not the same as your company outbound webhooks.

Exact abilities and behavior are documented under `**/docs`** → Endpoints / Security / Idempotency.

---

## OAuth2 (end users authorizing an agent)

Standard Laravel Passport routes (not under `/api/v1`):

- `GET /oauth/authorize` — user signs in (if needed) and approves scopes
- `POST /oauth/authorize` — approve/deny consent (full browser form post; avoid XHR-only clients for the redirect back to your `redirect_uri`)
- `POST /oauth/token` — exchange `code` for tokens
- `POST /oauth/token/refresh` — refresh tokens

Register clients in the dashboard: `**/company/oauth-apps**`. Scopes available are configured in `config/budera.php` (`wallet:read`, `wallet:pay`, …). **Public** clients should use **PKCE** (`code_challenge` / `code_challenge_method`) on `/oauth/authorize`.

---

## What an end user does in the browser (trying the product)

These flows are **web routes**, not JSON APIs—good for manual QA and understanding how humans interact with Budera.

1. **Create a Budera login**
  - Register at `**/register`** (Fortify), then sign in. Email verification may apply depending on configuration.
2. **Attach the user to a company** (one of):
  - **Founder path:** After login, `**/onboarding`** → create a company (`POST /onboarding/company`). User becomes **company owner** and can reach `**/dashboard`** and `**/company/***`.
  - **Team path:** A company owner invites the user’s email from `**/company/team`**. The user registers or signs in, then opens the link `**/invitations/{token}**` to accept and join as **company_developer** (see `OnboardingController`).
3. **Things an end user might do next (examples)**
  - `**/dashboard`** — main hub  
  - `**/company/wallets**` — view wallets your product created (via API) for users in that company  
  - `**/my-agents**` — see OAuth tokens / connected agents for **their** account  
  - `**/payment-approvals/{token}`** — approve or deny a held payment (when spend controls require it)  
  - Hosted `**/bank-link/{sessionToken}**` or `**/kyc/{sessionToken}**` when your product sends them through bank linking or KYC steps

Your **AI product** typically creates wallets and activity via `**/api/v1`** using a company API key or a user’s OAuth token, while humans use the web UI for consent, linking banks, and approvals.

---

## What the AI company does in the dashboard (integration setup)

After onboarding, company members with permission can:


| Area               | Route (examples)                 | Purpose                                                             |
| ------------------ | -------------------------------- | ------------------------------------------------------------------- |
| API keys           | `/company/api-keys`              | Create sandbox/live keys and assign abilities                       |
| OAuth apps         | `/company/oauth-apps`            | Register `redirect_uri`, get `client_id` / secret for the code flow |
| Webhooks           | `/company/webhooks`              | Receive event deliveries from Budera                                |
| Wallets / policies | `/company/wallets`, `.../policy` | Operate and tune spend controls                                     |


---

## Developer shortcuts (local)

- **`php artisan budera:demo`** — **one-command full setup**: seeds a demo AI company, developer, end user, wallets, bank link, OAuth client, and sandbox API key, then prints copy-paste curl commands for every API flow (wallet, payments, bank links, top-ups, transfers, KYC, OAuth). Use `--fresh` to wipe the database first.
- **`php artisan budera:token {email}`** — issue a **personal access token** for local testing (see command help for options).
- **`php artisan budera:credit-wallet act_xxxxxxxxxxxxxxxxxxxxxxxx [amount_cents]`** — **local/testing only:** ledger-credit a wallet so **`balance_cents`** increases (e.g. before retrying `POST /api/v1/payments`). Default amount is **10000** cents ($100). This does **not** replace a real ACH top-up in production.
- In-app reference: **`/docs`** (Overview, Quickstart, Authentication, Endpoints, Webhooks, Errors, Idempotency).

---

## Mental model

- **Company** = your AI product’s tenant in Budera (keys, OAuth clients, webhooks).  
- **User** = human with a Budera account; can belong to one or more companies via roles.  
- **Wallet** = financial identity for an end user under a company; agents act on wallets through **API keys** or **user-delegated OAuth tokens**, within **abilities** and policies.

If this file drifts from the code, prefer `**routes/api.php`**, `**routes/web.php**`, and `**resources/docs/api/endpoints.md**` as the source of truth.

---

## curl cookbook (`/api/v1`)

Set a base URL and Bearer token (sandbox **API key** from **Company → API Keys**, or an **OAuth access token**):

```bash
export BASE="http://localhost:8000"
export TOKEN="budera_sandbox_sk_..."   # paste your raw key or OAuth access token
```

**PowerShell:** `$BASE="http://localhost:8000"; $TOKEN="..."` then use `"$BASE/api/v1/..."` in curl.

Common JSON headers:

```bash
-H "Authorization: Bearer $TOKEN" \
-H "Accept: application/json" \
-H "Content-Type: application/json"
```

**Idempotency:** `POST` to `payments`, `topups`, and `transfers` **requires** header `Idempotency-Key: <unique string per logical operation>` (max 255 chars).

**Route IDs:** Wallet, bank link, payment, topup, and transfer routes use `**public_id`** in the path (e.g. wallet accounts use prefix `**act_**` not `acct_`, bank links `bl_`, payments `pay_`, topups `top_`, transfers `txfr_`).

**Wallet `GET` 404 vs 403:** A missing or unknown `act_…` id returns `**404`** with `error.code: resource_not_found`. If the id exists but belongs to **another company**, or the wallet **environment** (sandbox vs live) does not match your **API key’s environment**, the API returns `**403`** with `wallet_not_in_company` or `wallet_environment_mismatch` and details — not a fake “not found.”

---

### Wallet

**Current wallet context**

```bash
curl -sS "$BASE/api/v1/wallet/me" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Create wallet** (needs `wallet:pay`)

```bash
curl -sS -X POST "$BASE/api/v1/wallet/accounts" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{}'
```

**Get wallet by public id** (needs `wallet:read`; replace `act_...`)

```bash
curl -sS "$BASE/api/v1/wallet/accounts/act_xxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Submit KYC** (needs `wallet:pay`)

```bash
curl -sS -X POST "$BASE/api/v1/wallet/accounts/act_xxxxxxxxxxxxxxxxxxxxxxxx/kyc" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"legal_name":"Jane Doe","date_of_birth":"1990-01-15","address_line1":"1 Main St","last4_ssn":"1234"}'
```

---

### Payments

**List** (optional `?wallet_account_id=act_...`)

```bash
curl -sS "$BASE/api/v1/payments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"

curl -sS "$BASE/api/v1/payments?wallet_account_id=act_xxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Create** (needs `wallet:pay` + **Idempotency-Key**)

```bash
curl -sS -X POST "$BASE/api/v1/payments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: payment-req-001" \
  -d '{"wallet_account_id":"act_xxxxxxxxxxxxxxxxxxxxxxxx","amount_cents":1000,"payee_ref":"vendor-123","category":"software"}'
```

**Show**

```bash
curl -sS "$BASE/api/v1/payments/pay_xxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

---

### Top-ups

**List**

```bash
curl -sS "$BASE/api/v1/topups" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Create** (needs `wallet:topup` + **Idempotency-Key**; `bank_link_id` is the link’s `public_id` like `bl_...`, must be **verified**)

```bash
curl -sS -X POST "$BASE/api/v1/topups" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: pay-topup-001" \
  -d '{"wallet_account_id":"act_xxxxxxxxxxxxxxxxxxxxxxxx","bank_link_id":"bl_xxxxxxxxxxxxxxxxxxxxxxxx","amount_cents":5000}'
```

**Show**

```bash
curl -sS "$BASE/api/v1/topups/top_xxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

---

### Transfers

**List**

```bash
curl -sS "$BASE/api/v1/transfers" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Create** (needs `wallet:transfer` + **Idempotency-Key**)

```bash
curl -sS -X POST "$BASE/api/v1/transfers" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: transfer-001" \
  -d '{"from_wallet_account_id":"act_aaaaaaaaaaaaaaaaaaaaaaaa","to_wallet_account_id":"act_bbbbbbbbbbbbbbbbbbbbbbbb","amount_cents":2500}'
```

**Show**

```bash
curl -sS "$BASE/api/v1/transfers/txfr_xxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

---

### Ledger

```bash
curl -sS "$BASE/api/v1/wallets/act_xxxxxxxxxxxxxxxxxxxxxxxx/ledger" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

---

### Bank links

**Create — micro-deposit path (routing + account number)** (needs `wallet:link`)

```bash
curl -sS -X POST "$BASE/api/v1/bank-links" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"routing_number":"021000021","account_number":"1234567890","bank_slug":"mock","environment":"sandbox"}'
```

**Create — hosted session for an end user** (needs `wallet:link`; user must belong to your company)

```bash
curl -sS -X POST "$BASE/api/v1/bank-links" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"end_user_email":"human@example.com","environment":"sandbox"}'
```

**Show**

```bash
curl -sS "$BASE/api/v1/bank-links/bl_xxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Verify micro-deposits** (amounts in cents; sandbox often matches `config/budera.bank_link.sandbox_microdeposit_cents`)

```bash
curl -sS -X POST "$BASE/api/v1/bank-links/bl_xxxxxxxxxxxxxxxxxxxxxxxx/verify" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"amount_first_cents":12,"amount_second_cents":34}'
```

**Delete**

```bash
curl -sS -X DELETE "$BASE/api/v1/bank-links/bl_xxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

---

### Payment approvals (held payments)

```bash
curl -sS -X POST "$BASE/api/v1/approvals/TOKEN_FROM_HELD_PAYMENT/approve" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{}'

curl -sS -X POST "$BASE/api/v1/approvals/TOKEN_FROM_HELD_PAYMENT/deny" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{}'
```

Replace `TOKEN_FROM_HELD_PAYMENT` with the real approval token from your flow or webhooks.

---

### Sandbox simulation (sandbox key + `sandbox:simulate` + sandbox company context)

**Simulate settlement** (`bank_transfer_id` comes from payment/topup metadata in sandbox)

```bash
curl -sS -X POST "$BASE/api/v1/sandbox/simulate/settlement" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"bank_transfer_id":"mock-transfer-id-from-metadata"}'
```

**Simulate ACH return** (after settlement)

```bash
curl -sS -X POST "$BASE/api/v1/sandbox/simulate/return" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"bank_transfer_id":"mock-transfer-id-from-metadata"}'
```

**Simulate KYC approve** (numeric DB id)

```bash
curl -sS -X POST "$BASE/api/v1/sandbox/simulate/kyc-approve" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"wallet_kyc_verification_id":42}'
```

**Reveal sandbox micro-deposit amounts**

```bash
curl -sS -X POST "$BASE/api/v1/sandbox/simulate/microdeposit" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"bank_link_id":"bl_xxxxxxxxxxxxxxxxxxxxxxxx"}'
```

**Force KYC approve (alternate route)** — `walletKycVerification` is resolved by route key (see model); often numeric id in URL:

```bash
curl -sS -X POST "$BASE/api/v1/sandbox/kyc/approve/42" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

---

## OAuth2 token exchange (Passport)

**Authorization code → access token** (confidential client; adjust `client_id`, `client_secret`, `redirect_uri`, and `code`)

```bash
curl -sS -X POST "$BASE/oauth/token" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "authorization_code",
    "client_id": "YOUR_CLIENT_UUID",
    "client_secret": "YOUR_CLIENT_SECRET",
    "redirect_uri": "https://your-app.example/oauth/callback",
    "code": "AUTHORIZATION_CODE_FROM_BROWSER_REDIRECT"
  }'
```

**Refresh token**

```bash
curl -sS -X POST "$BASE/oauth/token" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "refresh_token",
    "refresh_token": "YOUR_REFRESH_TOKEN",
    "client_id": "YOUR_CLIENT_UUID",
    "client_secret": "YOUR_CLIENT_SECRET"
  }'
```

Public clients use PKCE and typically send `code_verifier` instead of `client_secret`; see Passport docs and your client settings.

---

## Mock bank inbound webhook (operator / integration tests)

`POST /api/webhooks/mock-bank` expects body signed with HMAC-SHA256 using the partner integration’s **inbound** secret (`X-Signature: sha256=<hex>`). This is **not** the same as calling `/api/v1` with an API key—only use if you have configured mock bank webhook secrets.

```bash
# Example only — compute BODY then SIGN=HMAC_SHA256(secret, BODY); send X-Signature: sha256=$SIGN
curl -sS -X POST "$BASE/api/webhooks/mock-bank" \
  -H "Content-Type: application/json" \
  -H "X-Signature: sha256=REPLACE_WITH_COMPUTED_HEX" \
  -d '{"event":"kyc.verified","data":{"kyc_submission_id":"..."}}'
```

