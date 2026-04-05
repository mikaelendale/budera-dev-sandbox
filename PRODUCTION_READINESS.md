# Budera — Production Readiness Audit

**Date:** 2026-03-29 (initial audit) · **Last updated:** 2026-03-29 (codebase follow-up)  
**Audited commit:** `master` (current working tree)  
**Score: 27 / 42 checks full pass (✅)** · **11 partial (⚠️)** · **4 not met (❌)**

### Follow-up implemented in codebase (since initial audit)

- **Ledger / demo:** `DemoSandboxSeeder` seeds opening balances via `LedgerService::credit()` (wallets start at `balance_cents: 0`).
- **Payment race (mitigation):** Outbound ACH debits the **ledger at authorization** (after approval, before `achPush`); settlement webhooks **reuse** `settlement_ledger_entry_id` where present; failures reverse the auth debit when applicable.
- **Compliance:** `ComplianceScreen::evaluate()` runs **synchronously** in `SpendControlsPipeline` (OFAC, high-risk payee, structuring hold). `RunComplianceScreenJob` remains for async `run()` if dispatched elsewhere.
- **Post-approval flow:** `ApprovalService` calls `PaymentService::submitApprovedPaymentToAch()` after token approval.
- **Sandbox:** `EnsureSandboxEnvironment` returns **`sandbox_disabled_production` (404)** when `config('app.env') === 'production'`.
- **Rate limits:** `api-company` limiter uses **`api_key:{id}`** when the request resolves an API key; OAuth-only traffic still uses `company_api:{companyId}` or IP fallback.
- **Webhooks:** `config/webhook-server.php` → **`X-Budera-Signature`**; `PaymentService`, `MockBankWebhookController`, and `SimulationController` pass `webhook_event` / `webhook_payload` for listed payment/topup events; `config/budera.php` `outbound_webhook_events` expanded.
- **API:** `WalletAccountController::show` returns `balance_usd`, `policy`, `agent_id`, `bank_link`; `WalletController::me` returns API-key `company` + `environment`, OAuth `token_expires_at` + `linked_accounts`.
- **API webhook test:** `POST /api/v1/company/webhooks/{webhookEndpoint}/test` with `webhooks:manage` ability.
- **Logging:** Monolog processor redacts sensitive context keys on `single` / `daily` channels.
- **Jobs:** Optional `correlationId` on `ProcessWebhookDeliveryJob`, `RunComplianceScreenJob`, `DispatchWebhookOutboxJob` (+ `Log::shareContext` when set); dashboard test dispatch passes request correlation when present.
- **OAuth audit:** `Token::created` writes a `DomainAuditLog` row for **`oauth.access_token.issued`** (scopes, client, company).
- **CLI:** `php artisan budera:reconcile` aliases `ledger:reconcile`.
- **Routing:** `WalletAccountRouteBinding` uses `withoutGlobalScope('company')` so foreign-wallet / env-mismatch return **403** JSON errors, not **404**.

**Still intentionally / documented as open:** queue driver **`database`** (not Redis) per product choice; `TransferService` / `TopupService` **initiation** paths may still omit developer webhooks; no concurrent payment **Pest** test yet; production **infra** (SSL, backups, Sentry, branch protection) unchanged from audit.

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Passes — implemented correctly |
| ⚠️ | Partially implemented — gaps noted |
| ❌ | Not implemented or broken |
| 🔴 | Severity: blocker |
| 🟠 | Severity: required |
| 🟡 | Severity: high / important |

---

## 1. Money Integrity & Ledger (6 / 7 pass)

### 🔴 ✅ Every balance change writes a ledger_entry row (demo / seeded paths)

**Done:** `DemoSandboxSeeder` creates wallets with `balance_cents: 0` and applies opening credits via `LedgerService::credit(..., 'manual_credit', ...)`.

**Remaining gap (if any):** Other code paths that still write `balance_cents` without a ledger row (e.g. ad-hoc scripts) should be audited separately. Registration flows were not re-scanned in this pass.

---

### 🔴 ✅ Ledger writes are inside Postgres transactions with row-level locking

`LedgerService::postEntry()` wraps everything in `DB::transaction()` and locks the wallet row with `lockForUpdate()` before computing the new balance. **Passes.**

---

### 🔴 ✅ ACH return correctly reverses the ledger

Both `MockBankWebhookController::handleAchTransferWebhook` (webhook path) and `SimulationController::returnPaymentInline` (inline path) load the `settlement_ledger_entry_id` from payment metadata and call `LedgerService::reversal()`, then transition the payment to `PaymentReturned`. **Passes.**

> **Note:** There is no topup-return reversal path — ACH returns are only handled for outbound payments, not inbound topups.

---

### 🔴 ✅ No negative balances possible (pre-bank ledger debit)

`LedgerService::postEntry()` still throws `InsufficientBalanceException` when a debit would produce a negative `balance_after_cents`.

**Done:** Outbound payments **debit the ledger after approval and before `achPush`** (authorization hold in `ledger_entries`). `MockBankWebhookController` / inline simulation **reuse** `settlement_ledger_entry_id` on settle when the entry exists to avoid double debit; ACH failure/return paths **reverse** the auth debit when present.

**Residual risk:** Add an explicit **concurrent double-POST** Pest test to lock in behavior under parallel requests (see §8.2).

---

### 🔴 ✅ Idempotency enforced on all mutating money endpoints

`EnsureIdempotency` middleware is applied to `POST /payments`, `POST /topups`, and `POST /transfers`. Missing `Idempotency-Key` returns `IDEMPOTENCY_KEY_REQUIRED`. Same key + same payload returns the cached response; same key + different payload returns `409 IDEMPOTENCY_KEY_CONFLICT`. **Passes.**

> **Gap:** `POST /wallet/accounts`, `POST /bank-links`, and all sandbox `simulate/*` routes do NOT have the idempotency middleware. This is acceptable for non-money endpoints.

---

### 🔴 ✅ Seeder credits write ledger entries

**Done:** Demo sandbox seeding uses `LedgerService::credit()` for opening balances. `CreditWalletCommand` continues to use `LedgerService`.

---

### 🟠 ✅ Daily reconciliation command exists and is scheduled

`php artisan ledger:reconcile` is scheduled daily in `routes/console.php`. **`php artisan budera:reconcile`** is registered as an alias (same handler).

---

## 2. Spend Controls Pipeline (4 / 5 pass)

### 🔴 ✅ Layer 1 — Policy gate fully wired

`PolicyGate` enforces: `per_tx_limit_usd`, `daily_spend_limit_usd`, `daily_tx_count`, `allowed_categories`, `blocked_payees`, `business_hours_only`, and `max_new_payees_per_day`. **All listed fields implemented. Passes.**

---

### 🔴 ✅ Layer 2 — Balance gate wired

`BalanceGate` compares `balance_cents >= amountCents` before approving. Returns `insufficient_balance` rejection or `heldNeedsTopup()` if auto-topup is enabled. Called from `SpendControlsPipeline` before bank submission. **Passes.** Ledger balance is aligned with opening credits and pre-bank debits (see §1.4).

---

### 🔴 ✅ Layer 3 — Velocity / anomaly detection wired

`VelocityEngine` checks 24h payment count against `velocity_sensitivity`-derived threshold, unusual payee diversity (`max_new_payees_per_day`), and amount standard-deviation anomaly. Returns `heldAnomaly()` decision. **Passes.**

---

### 🟠 ✅ Layer 4 — Approval threshold wired

`ApprovalGate` creates an `ApprovalRequest` with token and expiry when `amount_usd > require_approval_above`. `ApprovalService` handles token resolution and payment resumption. Notification sent via `PaymentHeldForApprovalNotification`. **Passes.**

---

### 🟠 ⚠️ Layer 5 — OFAC / compliance check wired (sync; not production-grade screening)

**Done:** `ComplianceScreen::evaluate()` runs **inside `SpendControlsPipeline`** before an approved decision is returned. Holds for **OFAC-style patterns**, **high-risk payee** patterns, and **structuring** create flags and return `heldAnomaly()` before bank submission. `RunComplianceScreenJob` still exists for `run()` / legacy dispatch but is **not** used on the main “all gates pass” pipeline path for blocking.

**Gap:** Screening remains **substring / heuristic**, not an external sanctions API (OFAC SDN, etc.). **Fix for production:** integrate a real sanctions / compliance provider and replace pattern lists.

---

## 3. Security (7 / 8 pass)

### 🔴 ✅ Sandbox endpoints hard-blocked when app env is production

**Done:** `EnsureSandboxEnvironment` returns **`sandbox_disabled_production` (404)** when `config('app.env') === 'production'` (before API key checks).

**Still true:** Live API keys remain blocked from simulation routes by **`environment !== sandbox`** on the key. Sandbox keys cannot call simulate endpoints in production deploys.

---

### 🔴 ✅ Sandbox keys cannot touch live data

`WalletAccount` global scope applies both `company_id` and `environment` filters from `CompanyContext`. A sandbox API key sets `environment=sandbox` in the context, so any query for a live wallet returns 404 (not found by global scope). **Passes.**

---

### 🔴 ✅ Sensitive columns encrypted at field level

`BankLink` model uses `'encrypted_routing' => 'encrypted'` and `'encrypted_account' => 'encrypted'` Eloquent casts. **Passes.**

---

### 🔴 ✅ API keys stored as hashes, never plaintext

`ApiKey` model has no plaintext key column. `ApiKeyGuard` looks up by `hash('sha256', $bearerToken)` against `key_hash`. **Passes.**

---

### 🔴 ✅ Outgoing webhooks HMAC-signed (header name)

**Done:** `config/webhook-server.php` sets **`signature_header_name` → `X-Budera-Signature`**. `ProcessWebhookDeliveryJob` uses the signer’s `signatureHeaderName()`.

**Still open:** Legacy **`DispatchWebhookOutboxJob`** path uses Spatie `WebhookCall::dispatchSync()` inside the job — consider migrating to `ProcessWebhookDeliveryJob` for consistent retries.

---

### 🟠 ❌ OAuth tokens scoped and non-escalatable

`CheckApiKeyAbility` middleware checks `$user->tokenCan($ability)` for Passport tokens — scopes are enforced per-endpoint. **This works correctly.**

**Gap:** `wallet/me` returns the same wallet data regardless of whether the OAuth token has `wallet:read` or `wallet:pay` scope. Scope enforcement on read vs write is present, but the `WalletController::me` endpoint resolution is identical for both, and no "wrong user's wallet" cross-tenant test is confirmed.

**Not clearly failing** — marking as partial pending dedicated test verification.

---

### 🟠 ✅ Rate limiting per API key (when API key auth)

**Done:** For resolved **API keys**, the limiter key is **`api_key:{apiKeyId}`** (tier still from the key’s company). **OAuth-only** requests continue to use **`company_api:{companyId}`** or **IP** fallback when no company context exists.

---

### 🟠 ⚠️ No secrets in logs, responses, or error messages

**Done (logs):** `App\Logging\RedactSensitiveLogProcessor` is registered via tap on **`single`** and **`daily`** channels; scrubs common sensitive **context keys** (e.g. `authorization`, `password`, `bearer`, `routing`, `account_number`, …).

**Gap:** Request **headers** are not globally stripped from log processors; API **responses** / **error detail** payloads were not audited in this pass. Expand redaction if logs include full request arrays.

---

## 4. API Completeness & Response Quality (3 / 5 pass)

### 🟡 ✅ Account response includes balance, policy summary, agent_id, bank link

**Done:** `GET /api/v1/wallet/accounts/{walletAccount}` returns **`balance_usd`**, **`agent_id`**, **`policy`** (summary fields), **`bank_link`** (`id`, `status`, `account_last4`), plus `id` / `environment` / `status`. No dedicated `WalletAccountResource` class — payload is assembled in the controller.

---

### 🟠 ✅ wallet/me returns richer context (API key vs OAuth)

**Done:** **API key:** `company` (`id`, `name`, `logo_url`), `environment` (key environment), enriched `wallet` (`balance_usd`, `agent_id`, string `status`). **OAuth (Passport):** `token_expires_at` (ISO8601), `linked_accounts[]` (`id`, `status`, `account_last4`) for the user in the current company. **`scopes`** unchanged.

**Gap:** No separate opaque **`user_token`** field (by design — avoid echoing raw tokens); adjust API spec if that name was required for something other than `token_expires_at`.

---

### 🟠 ⚠️ All 30+ error codes from the spec implemented

**Canonical source:** `config/api_errors.php` (throws `InvalidArgumentException` if code missing). **Added in follow-up:** `sandbox_disabled_production`. Many codes remain **ad-hoc** vs a published external spec; **tests** reference `tests/Fixtures/expected_api_error_codes.php` for inventory parity.

**Fix needed:** Publish a single **developer-facing** error catalog (docs or OpenAPI) aligned with `config/api_errors.php`.

---

### 🟠 ✅ Pagination consistent across all list endpoints

All list endpoints (`payments`, `topups`, `transfers`, `ledger`) use `cursorPaginate()` and return `next_cursor`/`prev_cursor` in `meta`. **Passes** — minor inconsistency: ledger uses 100 per page vs 50 for others.

---

### 🟡 ❌ Idempotency-Key required on POST /payments, /topups, /transfers

`EnsureIdempotency` returns `IDEMPOTENCY_KEY_REQUIRED` for missing keys — so the behavior is correct. **However**, the current `DemoCommand` and API documentation pass the key as optional in curl examples (`Idempotency-Key: demo-pay-001`). This is only a documentation/UX issue, not a code bug. Marking partial fail for spec compliance.

> Actually re-checking: middleware does enforce it. **This passes** — see section 1.4.

---

## 5. Webhook System (3 / 4 pass)

### 🟡 ⚠️ Every state transition fires the correct webhook event

**Done:** `PaymentService` emits **`payment.approved`**, **`payment.processing`**, **`payment.failed`**, **`payment.held.approval_required`**, **`payment.held.anomaly`** (via `DeveloperWebhookContext`). **`MockBankWebhookController`** and **`SimulationController`** emit **`payment.settled`**, **`payment.returned`**, **`payment.failed`** (ACH fail), **`topup.settled`**, **`topup.failed`** where applicable. **`config/budera.php`** `outbound_webhook_events` lists the new event names for subscription validation.

**Still missing / not wired in this pass:**
- **`TransferService`** — e.g. **`transfer.completed`** (and related) not confirmed with `webhook_event` / `webhook_payload`.
- **`TopupService`** — **initiation** transitions (e.g. processing/failed before bank webhook) may still lack developer webhooks; bank-driven settle/fail paths on mock/simulation are covered via controllers above.

**What fires (unchanged):** `account.active`, KYC/KYB, account freeze/unfreeze, `live.enabled`, plus payment/topup events listed above.

---

### 🟠 ✅ Webhook delivery is queued, retried, and logged

`ProcessWebhookDeliveryJob` — `ShouldQueue`, 5 tries, exponential backoff (`[5, 30, 120, 600, 3600]` seconds), `WebhookDelivery` log rows in DB. **Passes.**

> Legacy `DispatchWebhookOutboxJob` uses `dispatchSync()` inside the job — this path has no independent retry. Should be migrated to `ProcessWebhookDeliveryJob`.

---

### 🟠 ✅ Webhook payload includes environment field

`TransitionRecorder::enqueueWebhook()` merges `environment` into every payload. `WebhookOutboxPayloadFactory` normalizes it into the top-level shape. **Passes.**

---

### 🟡 ✅ Test fire endpoint via API

**Done:** **`POST /api/v1/company/webhooks/{webhookEndpoint}/test`** with auth **`webhooks:manage`** (API key or OAuth with scope). Same payload shape as dashboard test (`test.ping` via `WebhookOutboxPayloadFactory`). **API keys:** company must match endpoint (`company_id`); **OAuth:** `authorizeForUser` + policy when not using an API key.

---

## 6. Compliance & Audit Trail (1 full ✅ · 3 partial ⚠️)

### 🟡 ⚠️ Domain audit log fires on every significant action

`TransitionRecorder::record()` always writes a `DomainAuditLog` row. However, many controllers record audit directly via `AuditService::recordDomainAudit()` (API keys, OAuth clients, webhooks, invitations, policies, live access, KYB). 

**Gap:** Several money-adjacent actions have no audit record at all — e.g. `POST /bank-links` store, `POST /wallet/accounts` store, OAuth `authorize` grant, approval `approve`/`deny` calls (these may have it — unconfirmed).

**Partial** — coverage is strong for modeled state machine transitions, incomplete elsewhere.

---

### 🟠 ✅ State transitions table populated on every status change

`TransitionRecorder::record()` always writes a `StateTransition` row alongside the domain audit. **Passes for transitions that go through `TransitionRecorder`.** 

> Any status change that bypasses `TransitionRecorder` (e.g. direct `$model->status->transitionTo()` without `record()`) will not get a row.

---

### 🟠 ⚠️ correlation_id threads through async jobs

**Done:** All three jobs accept an **optional** `?string $correlationId` constructor arg; **`ProcessWebhookDeliveryJob::handle()`** calls **`Log::shareContext(['correlation_id' => …])`** when non-empty. **Dashboard / API test** webhook dispatch passes **`CorrelationId::current($request)`** when using `dispatchSync`.

**Gap:** **`webhooks:dispatch`** and other queue dispatches often pass **`null`**; no universal middleware forces correlation onto every queued job. **Fix:** pass correlation from `CorrelationId::fromRequestOrGenerate()` at every dispatch site, or use a queue middleware.

---

### 🟠 ⚠️ OAuth authorization is logged with timestamp and granted scopes

**Done:** On **`Token::created`** (after successful issuance), **`AuditService::recordDomainAudit`** writes **`oauth.access_token.issued`** with **user_id**, **client_id**, **company_id** (from client), **scopes**, **correlation_id**, IP/UA when request exists.

**Gap:** The **browser consent step** at `/oauth/authorize` (approve/deny *before* token creation) is still not a separate audited event unless you add an explicit approve/deny hook. Treat token issuance as the primary audit trail for “grant completed.”

---

## 7. Infrastructure & Operations (0 full ✅ · 1 intentional ⚠️ · 4 ❌)

### 🟡 ⚠️ Redis queue driver — not database

Default queue driver remains **`database`** (SQLite-friendly). **Product decision (this repo):** keep **`database`** until ops explicitly moves to Redis.

**For production at scale:** set **`QUEUE_CONNECTION=redis`**, configure **Horizon** for `payments`, `webhooks`, `notifications`, `compliance`, `default` (see `config/budera.php` `queue_listen`).

---

### 🔴 ❌ DB connection uses sslmode=require

Current `.env` uses SQLite (`DB_CONNECTION=sqlite`) for local development. No SSL configuration is present. PostgreSQL SSL enforcement must be added for the production environment (`pgsql?sslmode=require` in DSN or `DB_SSLMODE=require`).

---

### 🔴 ❌ Automated DB backups with point-in-time recovery

No backup configuration is present in the repository. This is infrastructure-level (RDS, Neon, Supabase, etc.) and cannot be verified from code, but no backup strategy is documented.

---

### 🔴 ❌ Zero secrets in repo or deployment config files

`.env` is in `.gitignore` — good. However:
- `MOCK_BANK_SECRET` and `MOCK_BANK_WEBHOOK_SECRET` values are visible in `.env.example`
- Passport RSA keys are typically stored in `storage/` and may be committed
- Run `git log --all -S "sk_live"` to confirm no historic secret exposure

**Verify:** `php artisan passport:keys` output location; confirm `storage/oauth-*.key` is in `.gitignore`.

---

### 🔴 ❌ Nightwatch connected and alerting on exceptions + slow queries

No Nightwatch (or equivalent — Sentry, Flare, Bugsnag) configuration found in codebase. No error monitoring service connected. No slow query alerting configured.

---

## 8. Testing Coverage (2 / 4 full ✅ · 1 partial ⚠️ · 1 ❌)

### 🟡 ✅ Every spend control layer has unit tests

Spend controls are covered by `SandboxSimulationTest`, `SpendControlsPipelineTest`, `ApprovalServiceTest`, `KycWalletActivationTest`, `MockBankWalletApiTest`, `FullAgentMoneyPathTest`, `ApiKeyAuthTest`, and related API tests. **Layer 5** is now exercised **synchronously** in the pipeline (compliance holds before bank); add targeted tests for **OFAC / high-risk / structuring** strings if not already present.

---

### 🔴 ❌ Concurrent payment test — race condition on balance

No test spawns two parallel HTTP requests against the same wallet. The race condition (two `$60` payments against a `$100` wallet) is a known gap identified in item 1.4. No concurrent test exists to prove only one succeeds.

**Fix needed:** Add a Pest test using parallel processes or database-level lock testing.

---

### 🟠 ✅ Idempotency tested for duplicate requests

`EnsureIdempotency` middleware is tested — duplicate keys return cached responses, different body with same key returns 409. **Passes.**

---

### 🔴 ❌ CI pipeline blocks merge on test failure

`.github/workflows/ci.yml` exists and runs `php artisan test --compact` on push/PR to `develop`, `main`, `master`. **However:**
- There is no branch protection rule enforced from this file alone — that requires GitHub repo settings
- The CI uses SQLite in-memory (no separate test DB) which masks some Postgres-specific issues
- No concurrent/race condition tests in CI

**Partially passes** — CI exists and runs tests, but branch protection and Postgres in CI are unconfirmed.

---

## Summary Table

| # | Category | Check | Severity | Status |
|---|----------|-------|----------|--------|
| 1.1 | Ledger | Every balance change writes ledger_entry | 🔴 blocker | ✅ Demo seeder uses `LedgerService` |
| 1.2 | Ledger | Ledger writes in transactions with row lock | 🔴 blocker | ✅ |
| 1.3 | Ledger | ACH return reverses the ledger | 🔴 blocker | ✅ |
| 1.4 | Ledger | No negative balances / no double-spend to bank | 🔴 blocker | ✅ Pre-bank ledger debit + settle reuse |
| 1.5 | Ledger | Idempotency on all mutating endpoints | 🔴 blocker | ✅ |
| 1.6 | Ledger | Seeder/admin credits write ledger entries | 🔴 blocker | ✅ Seeder fixed; admin via command |
| 1.7 | Ledger | Daily reconciliation command scheduled | 🟠 required | ✅ `ledger:reconcile` + `budera:reconcile` |
| 2.1 | Spend Controls | Layer 1 — Policy gate | 🔴 blocker | ✅ |
| 2.2 | Spend Controls | Layer 2 — Balance gate | 🔴 blocker | ✅ |
| 2.3 | Spend Controls | Layer 3 — Velocity/anomaly | 🔴 blocker | ✅ |
| 2.4 | Spend Controls | Layer 4 — Approval threshold | 🟠 required | ✅ + post-approval ACH resume |
| 2.5 | Spend Controls | Layer 5 — OFAC/compliance | 🟠 required | ⚠️ Sync + heuristic; not external API |
| 3.1 | Security | Sandbox blocked when app env production | 🔴 blocker | ✅ `sandbox_disabled_production` |
| 3.2 | Security | Sandbox keys cannot touch live data | 🔴 blocker | ✅ |
| 3.3 | Security | Sensitive columns field-encrypted | 🔴 blocker | ✅ |
| 3.4 | Security | API keys stored as hashes | 🔴 blocker | ✅ |
| 3.5 | Security | Webhooks HMAC + header name | 🔴 blocker | ✅ `X-Budera-Signature` |
| 3.6 | Security | OAuth tokens scoped | 🟠 required | ⚠️ Enforcement exists; cross-tenant untested |
| 3.7 | Security | Rate limit per API key | 🟠 required | ✅ `api_key:{id}` when key auth |
| 3.8 | Security | No secrets in logs/responses | 🟠 required | ⚠️ Context redaction on single/daily |
| 4.1 | API Quality | Account show: balance, policy, agent, bank link | 🟡 high | ✅ |
| 4.2 | API Quality | wallet/me richer context | 🟠 required | ✅ |
| 4.3 | API Quality | All 30+ error codes from spec | 🟠 required | ⚠️ `config/api_errors.php` + docs gap |
| 4.4 | API Quality | Pagination consistent | 🟡 important | ✅ (minor: ledger=100 vs others=50) |
| 4.5 | API Quality | Idempotency-Key enforced at middleware | 🟡 important | ✅ |
| 5.1 | Webhooks | Money state transitions fire webhooks | 🟡 high | ⚠️ Payment + mock/sim paths; transfer/topup service TBD |
| 5.2 | Webhooks | Delivery queued, retried, logged | 🟠 required | ✅ |
| 5.3 | Webhooks | Payload includes environment | 🟠 required | ✅ |
| 5.4 | Webhooks | Test fire endpoint via API | 🟡 important | ✅ `POST .../company/webhooks/{id}/test` |
| 6.1 | Compliance | Domain audit on every action | 🟡 high | ⚠️ Strong on transitions; not universal |
| 6.2 | Compliance | State transitions table populated | 🟠 required | ✅ (via TransitionRecorder) |
| 6.3 | Compliance | correlation_id in async jobs | 🟠 required | ⚠️ Optional on jobs; not all dispatchers |
| 6.4 | Compliance | OAuth token issuance logged | 🟠 required | ⚠️ `oauth.access_token.issued`; consent UI optional |
| 7.1 | Infra | Redis queue driver | 🟡 high | ⚠️ By design `database` here; Redis for prod scale |
| 7.2 | Infra | DB sslmode=require | 🔴 blocker | ❌ SQLite local; no SSL config |
| 7.3 | Infra | Automated DB backups + PITR | 🔴 blocker | ❌ Not configured |
| 7.4 | Infra | Zero secrets in repo | 🔴 blocker | ⚠️ .env gitignored; Passport keys unconfirmed |
| 7.5 | Infra | Error monitoring connected | 🔴 blocker | ❌ None |
| 8.1 | Tests | Spend control unit tests | 🟡 high | ✅ |
| 8.2 | Tests | Concurrent payment race test | 🔴 blocker | ❌ Still recommended |
| 8.3 | Tests | Idempotency duplicate test | 🟠 required | ✅ |
| 8.4 | Tests | CI blocks merge on failure | 🔴 blocker | ⚠️ CI exists; branch protection unconfirmed |

---

## Prioritised Fix List (remaining)

### Must fix before any real money (🔴 blockers)

1. **Error monitoring** — Connect Sentry, Flare, or equivalent before beta.
2. **DB backups + PITR** — Configure on managed Postgres (or provider equivalent).
3. **PostgreSQL SSL** — `sslmode=require` (or provider TLS) for production DSN; don’t ship prod on SQLite.
4. **Concurrent payment test** — Pest (or harness) proving two parallel `POST /payments` cannot both debit past available ledger balance.
5. **Production compliance** — Replace heuristic OFAC/high-risk/structuring with **external** sanctions / AML APIs where legally required.

### Must fix before developer beta (🟠 required)

6. **Transfer / topup webhooks** — Add `webhook_event` + `webhook_payload` on **`TransferService`** and **`TopupService`** state transitions not already covered by bank webhooks.
7. **correlation_id everywhere** — Pass correlation into **every** job dispatch (`webhooks:dispatch`, outbox, compliance) or add queue middleware.
8. **OAuth consent step (optional)** — Separate domain audit on approve/deny at `/oauth/authorize` if you need parity with “user clicked Allow.”
9. **Published error catalog** — Export **`config/api_errors.php`** to public API docs / OpenAPI.
10. **Cross-tenant OAuth tests** — Prove `wallet/me` and wallet routes cannot leak another company’s data.

### Fix before GA (🟡 high / important)

11. **Redis + Horizon** — When traffic warrants, `QUEUE_CONNECTION=redis` and supervisor/Horizon per `config/budera.php` queues.
12. **Migrate DispatchWebhookOutboxJob** — Prefer `ProcessWebhookDeliveryJob` for retries/consistency.
13. **Pagination** — Standardise list page sizes (e.g. ledger vs others).
14. **Log redaction** — Extend processors if request/response logging is enabled in prod.
15. **CI / branch protection** — Enforce required status checks on `main` / `develop`.
