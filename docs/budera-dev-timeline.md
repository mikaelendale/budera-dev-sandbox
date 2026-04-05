# Budera — design-first development plan

**Single rule:** nothing in **migrations**, **policies**, or **routes** until the corresponding design artifact exists. Changing schema or money paths after launch = migrations on **live** money.

Align with `[tech-stack.md](./tech-stack.md)` (sandbox/live, webhooks, roles, Column vs Unit naming — this doc uses **Column Bank** as the banking integration target; swap vendor name in code if product chooses another).

---

## Index (01–18)


| #      | Topic                                                                                    | Gates                          |
| ------ | ---------------------------------------------------------------------------------------- | ------------------------------ |
| **01** | [Full ERD](#01--full-erd)                                                                | schema — blocks everything     |
| **02** | [Actor permission matrix](#02--actor-permission-matrix)                                  | RBAC — before controllers      |
| **03** | [Multi-tenancy scoping](#03--multi-tenancy-scoping-strategy)                             | security — company isolation   |
| **04** | [OAuth2 (Passport) — wallet auth](#04--oauth2--wallet-authorization-for-agents-passport) | OAuth — agent access           |
| **05** | [Column Bank mock adapter](#05--column-bank-mock-adapter--sandbox-banking-layer)         | sandbox — fake rails           |
| **06** | [State machines](#06--state-machines)                                                    | state machines — before models |
| **07** | [Full API contract](#07--full-api-contract)                                              | API design — dev spec          |
| **08** | [Spend controls pipeline](#08--spend-controls-pipeline)                                  | 5 layers, exact order          |
| **09** | [Ledger system](#09--ledger-system)                                                      | money — double-entry           |
| **10** | [KYC / KYB flows](#10--kyc--kyb-flows)                                                   | KYC / KYB                      |
| **11** | [Bank linking flow](#11--bank-linking-flow)                                              | bank link                      |
| **12** | [Webhook system](#12--webhook-system-design)                                             | webhooks                       |
| **13** | [Domain audit log](#13--domain-audit-log)                                                | audit                          |
| **14** | [Idempotency](#14--idempotency-system)                                                   | idempotency                    |
| **15** | [UI surfaces](#15--ui-surfaces)                                                          | UI design                      |
| **16** | [Testing strategy](#16--testing-strategy)                                                | testing                        |
| **17** | [Transactional email](#17--transactional-email-flows)                                    | email                          |
| **18** | [Infra + DevOps](#18--infra--devops-baseline)                                            | infra                          |


---

## 01 — Full ERD

**Goal:** every table, every column, every relationship — **source of truth** before migration `001`.

**Process:** draw in **dbdiagram.io** (or equivalent). Every FK, enum, nullable field decided here.

**Schema blocks everything downstream.**

### Core tables (starting list)


| Table                                                                  | Columns / notes (examples)                                                                                                                                                             |
| ---------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `companies`                                                            | `id`, `name`, `email`, `kyb_status`, `live_enabled_at`, `sandbox_limit_overrides`                                                                                                      |
| `api_keys`                                                             | `id`, `company_id`, `key_hash`, `environment` (`sandbox` | `live`), `abilities[]`, `revoked_at`                                                                                        |
| `users`                                                                | `id`, `company_id`, `user_token` (opaque ref), `kyc_status`, `kyc_approved_at`, `ofac_cleared`                                                                                         |
| `bank_links`                                                           | `id`, `user_id`, `status`, `bank_slug`, `account_last4`, `routing_hash`, `verified_at`, `revoked_at`                                                                                   |
| `accounts`                                                             | `id`, `company_id`, `user_id`, `agent_id` (ref), `environment`, `status`, `balance_usd` (computed/cached), `bank_account_ref`                                                          |
| `policies`                                                             | `id`, `account_id`, `per_tx_limit`, `daily_spend_limit`, `daily_tx_count`, `allowed_categories[]`, `blocked_payees[]`, `require_approval_above`, velocity rules, `business_hours_only` |
| `payments`                                                             | `id`, `account_id`, `status`, `amount`, `direction`, `rail` (`ach` | `rtp` | `card`), `payee_ref`, `idempotency_key`, `held_reason`, `approval_token`, `settled_at`                    |
| `topups`                                                               | `id`, `account_id`, `bank_link_id`, `status`, `amount`, `idempotency_key`, `settled_at`                                                                                                |
| `transfers`                                                            | `id`, `from_account_id`, `to_account_id`, `amount`, `status`, `idempotency_key`                                                                                                        |
| `ledger_entries`                                                       | `id`, `account_id`, `type` (`debit` | `credit`), `amount`, `reference_type`, `reference_id`, `balance_after`, `created_at`                                                             |
| `idempotency_keys`                                                     | `key`, `company_id`, `request_hash`, `response_body`, `created_at`                                                                                                                     |
| `webhook_endpoints`, `webhook_events`                                  | endpoint, `events[]`, secret, delivery log                                                                                                                                             |
| `domain_audit_log`                                                     | `stream` (`developer` | `agent_bank`), `actor_type`, `actor_id`, `action`, `resource_type`, `resource_id`, `metadata` JSONB, `correlation_id`                                          |
| `state_transitions`                                                    | `model_type`, `model_id`, `from`, `to`, `actor_type`, `actor_id`, `created_at`                                                                                                         |
| `kyc_sessions`, `kyb_reviews`, `compliance_flags`, `approval_requests` | —                                                                                                                                                                                      |


---

## 02 — Actor permission matrix

**Gate design before any controller.**

**Goal:** every **role**, every **action**, allow/deny. Map **every API endpoint** and **UI screen** to roles.


| Role                | Sketch of powers                                                                            |
| ------------------- | ------------------------------------------------------------------------------------------- |
| `budera_admin`      | Approve KYB, freeze accounts, view all data, issue **live** keys                            |
| `company_owner`     | Register, manage keys, all wallets, policies, approve payments                              |
| `company_developer` | View keys (not create live per policy), logs, **sandbox** test                              |
| `end_user`          | Link bank, personal limits, approve held payments, revoke agent access                      |
| `bank_partner`      | Read-only: transactions, KYB docs, reconciliation reports                                   |
| `agent`             | Pay, topup, transfer, read own ledger — **scoped to own account**; token abilities enforced |


**Deliverable:** one row per endpoint in a spreadsheet → becomes **Laravel Policy** classes + **Sanctum** token abilities.

---

## 03 — Multi-tenancy scoping strategy

**Wrong here = data leak between companies.**

- Every Eloquent model that belongs to a company gets **global scope** or `**BelongsToTenant` / `BelongsToCompany` trait** — **model level**, not only controller.
- **Middleware:** resolve `company` from API key → inject request context → **default query scope**.
- **Sandbox:** rows also scoped by `**environment`** — sandbox key **never** sees live rows (and vice versa).
- `**budera_admin`** bypasses tenant scope **explicitly** — not by accident.

**Recommendation:** simple `**BelongsToCompany` + global scope** — full control; optional **Spatie teams** or **stancl/tenancy** if needs grow.

---

## 04 — OAuth2 — wallet authorization for agents (Passport)

**How end users grant agents access to their Budera wallet.**

- **Flow:** AI company redirects user → Budera **OAuth consent** → user logs in / creates account → approves agent (limits, categories) → **access token** to AI company → used on behalf of agent.
- **Laravel Passport** — **Authorization Code** flow. AI company = **OAuth client**. User = **resource owner**. Wallet = **protected resource**.
- **Scopes (examples):** `wallet:read`, `wallet:pay`, `wallet:topup`, `wallet:transfer` — map 1:1 to agent token abilities.
- **Consent screen:** agent name, spend limits, category locks, thresholds — **plain language**.
- **Token:** tied to `user_id` + `company_id` + `account_id` + scopes + expiry; **revoke** from dashboard → token invalid → agent blocked.
- **Sandbox:** mock consent — **auto-approve** test flows; same token shape, no real session if desired.

This is the **highest-risk UX** for adoption: confused user = no authorization = no funded wallet.

---

## 05 — Column Bank mock adapter (sandbox banking layer)

**Fake Column Bank API** in sandbox — **same interface**, no real money.

- **Production:** omnibus, sub-accounts, ACH, webhooks, returns (R01–R10).
- **Mock:** same contract, instant responses, no rails. `**ColumnBankService`** interface → `**ColumnBankMock**` (sandbox) vs `**ColumnBankClient**` (live, TBD).
- Mock: fake routing/account (valid ABA format); ACH push → processing + internal event; `**/v1/sandbox/simulate/settlement**` → settled; `**/v1/sandbox/simulate/return**` → return webhook → ledger reversal.
- **Container:** bind mock vs live by `app.env` + **API key `environment`**.
- **Partner bank credentials** (API keys, webhook signing secrets, base URLs): **Budera admin only** — stored encrypted in `partner_bank_integrations`; **`ColumnBankService`** / `MockBankClient` resolve config from there with **env fallback** for local dev. **AI companies never** configure or see these.
- **Design the interface contract before** every Column endpoint is known — **payment pipeline** depends on the interface, not the concrete HTTP client.

---

## 06 — State machines

**Design all state diagrams before model classes.** Implement with `**spatie/laravel-model-states`**. Illegal transitions **throw** — not silent failures.

**Examples:**


| Domain        | Transitions (summary)                                                                                                              |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| **Payment**   | `pending` → `approved` → `processing` → `settled` | `failed` | `returned`; branches `held_anomaly`, `held_approval` → approve/deny |
| **Account**   | `pending` → `active` → `paused` | `closed`; `active` → `frozen` (admin) → `active` | `closed`                                      |
| **Topup**     | `pending` → `processing` → `settled` | `failed` | `returned`                                                                       |
| **Transfer**  | `pending` → `completed` | `failed`                                                                                                 |
| **KYC**       | `not_started` → `pending` → `approved` | `rejected` | `needs_info`                                                                 |
| **KYB**       | `pending` → `under_review` → `approved` | `rejected`                                                                               |
| **Bank link** | `initiated` → `microdeposit_sent` → `verified` | `failed` | `revoked`                                                              |
| **API key**   | `active` → `rotated` | `revoked`                                                                                                   |


Every transition → **domain audit** + `**state_transitions` row** + relevant **webhook**.

---

## 07 — Full API contract

**Spec developers integrate against** — hand-maintained docs (see `tech-stack.md`).

- Every endpoint: method, path, body, responses, **errors**.
- **Groups:** auth, accounts, payments, topups, transfers, ledger, kyc, bank-link, company, webhooks, sandbox.
- Mutating endpoints: `**Idempotency-Key`**, state transitions triggered, **webhooks fired**.
- **Error catalog:** 30+ codes, HTTP status, which **control layer** emits.
- **Sandbox-only** endpoints → middleware **hard-block** in live.

Formalize existing MVP spec into the **canonical doc** + PR checklist.

---

## 08 — Spend controls pipeline

**Five layers, exact order, exact behavior** — core product logic as a **chain**, not scattered `if` statements.

Document: layer order, failure response per layer, pass-through rules. (Detail locked in design doc before implementation.)

---

## 09 — Ledger system

**Double-entry discipline.** Never “just update balance.”

- Every movement → `**ledger_entries`**: `reference_type` (`payment`  `topup`  `transfer`  `fee`), `reference_id`, `amount`, `balance_after`.
- Balance = derived from ledger **or** cached field updated **inside same DB transaction** as ledger insert.
- ACH return → reversal entry + payment `returned`.
- All writes in **Postgres transactions** + **row locks** where needed for concurrent payments.
- **Reconciliation** job: sum of ledger per account = account balance.
- **No** admin “adjust balance” without a matching ledger line.

---

## 10 — KYC / KYB flows

**Hosted widget, vendor mock, status webhooks.**

- **KYB (company):** register → pending → **admin review** → approved → **live key** eligibility.
- **KYC (user):** `user_token` → `kyc_url` (hosted widget) → vendor webhooks → status → unlock account.
- **Sandbox:** mock session — `POST /v1/kyc/session` → mock URL; sandbox approval endpoint → `approved` without real Persona/Alloy.
- `**KycProvider` interface** + `**MockKycProvider`** — real vendor later without rewiring flows.
- **OFAC** on KYC approval (mock list in sandbox).
- Webhook handler: verify signature → job → update `kyc_status` → fire `kyc.*` to company webhooks.

---

## 11 — Bank linking flow

**Micro-deposit + mock bank adapter.**

- `POST /v1/bank-link/session` → hosted URL (routing + account).
- Store encrypted; mock micro-deposits (sandbox: instant, e.g. **$0.12** / **$0.34** — documented for devs).
- `POST /v1/bank-link/verify` → match → `verified` → topup unlocked.
- 3 failures → lock session.
- `**BankLinkService`** → `**MockBankLinkService**` (sandbox) → real ACH via Column (live).

---

## 12 — Webhook system design

**Outgoing** to AI company servers.

- **Event catalog (examples):** `payment.approved`, `payment.settled`, `payment.failed`, `payment.returned`, `payment.held.anomaly`, `payment.held.approval_required`, `topup.settled`, `topup.failed`, `kyc.approved`, `kyc.failed`, `account.frozen`, `bank_link.verified`, …
- **Observer** on transitions → **queued job** → `**spatie/laravel-webhook-server`** (sign + deliver + retry).
- **Payload:** `event`, `event_id`, `created_at`, `environment`, `data{}`.
- **Signing:** HMAC-SHA256, e.g. `X-Budera-Signature`.
- **Retries:** exponential backoff, cap attempts, then failed + logged.
- **DB delivery log** — attempts, status, latency, payload; visible in company dashboard.

---

## 13 — Domain audit log

**Two streams, one entry point** — `DomainLog::record()` only; no scattered `Log::` for business facts.


| Stream           | Examples                                                                           |
| ---------------- | ---------------------------------------------------------------------------------- |
| **Developer**    | Company registered, key created/rotated, webhook config, policy update, KYB status |
| **Agent + bank** | Payment attempts, ledger lines, state transitions, topups, compliance flags        |


**Shape:** `stream`, `actor_type`, `actor_id`, `action`, `resource_type`, `resource_id`, `metadata` JSONB, `correlation_id`, `environment`, `created_at`.

**correlation_id** threads async work — one payment attempt = one id across related rows.

---

## 14 — Idempotency system

**Middleware** on **all** mutating POSTs (payments, topups, transfers).

- Header `**Idempotency-Key`** required.
- Lookup `(key, company_id)` → if hit, return **cached response** (no double work).
- Else: process → store key + `request_hash` + response body.
- Same key + different body → **409** `IDEMPOTENCY_KEY_CONFLICT`.
- TTL e.g. **24h** (configurable).

---

## 15 — UI surfaces

**Four portals** — distinct auth boundaries.


| Portal                                | Contents                                                                                       |
| ------------------------------------- | ---------------------------------------------------------------------------------------------- |
| **Company dashboard** (Inertia/React) | Registration, API keys, wallets, ledger/history, webhooks, policy editor, **test mode** banner |
| **End-user app**                      | KYC widget, bank-link widget, payment approval, activity, agent access, revoke consent         |
| **Budera admin**                      | KYB queue, companies, freeze, compliance, AML, **live key** approvals, **partner bank integrations** (credentials) |
| **Bank partner**                      | Read-only tx, reconciliation export, KYB docs                                                  |


**KYC / bank-link widgets** are **hosted by Budera** — iframe or redirect inside AI company product; not embedded in company dashboard code.

---

## 16 — Testing strategy

**Fintech: tests before ship.**

- **Unit:** each spend layer, ledger math, state transitions, idempotency middleware.
- **Feature:** every API route — happy path + **every error code** from catalog.
- **Integration:** full payment path through **mock Column** + all 5 spend layers.
- **Concurrency:** two simultaneous debits against same wallet — correct balance / lock behavior.
- **Pest** + separate test DB + factories. **No** real money, bank, or KYC vendor in CI.

---

## 17 — Transactional email flows

**Design triggers + templates before locking provider.**

**Examples:** KYC started / approved / rejected; micro-deposit instructions; payment held for approval; low balance / anomaly / frozen; company KYB approved / live key granted.

**Provider:** **Resend** (DX) or **Postmark** (deliverability) — align with `[tech-stack.md](./tech-stack.md)`.

---

## 18 — Infra + DevOps baseline

- **Envs:** local, **staging**, **production** from day one; staging mirrors prod config.
- **Queues:** **Redis** (not DB). **Horizon**. Separate queues: `default`, `payments`, `webhooks`, `notifications` — payments never blocked by notifications.
- **Hosting:** choose early (Railway, Render, Fly.io, AWS/GCP). Managed Postgres + Redis (Neon, RDS, Supabase, Upstash, …).
- **CI/CD:** GitHub Actions — **Pest on every PR**, block merge on failure; staging auto-deploy, prod manual.
- **Secrets:** not in repo; provider secrets manager.

---

## After design lock

1. Run migrations in order.
2. Implement policies + middleware from **02** and **03**.
3. Build API against **07**; features against **06**, **08**, **09**, **14**.
4. Wire webhooks **12** and audit **13** as behaviors land.

---

## Quick links


| Doc                                | Purpose                                                                |
| ---------------------------------- | ---------------------------------------------------------------------- |
| `[tech-stack.md](./tech-stack.md)` | Packages, sandbox/live (single domain + keys), actors, email, webhooks |


---

*Design artifacts should be versioned (git) alongside code. Update this plan when phases complete or scope shifts.*