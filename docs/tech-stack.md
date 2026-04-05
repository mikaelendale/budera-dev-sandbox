# Budera — tech stack reference

Single place for **what we use and why** (Postgres, auth, API, sandbox/live, API docs, RBAC, OAuth, observability, transactional email, domain audit, state machines). Update this when choices change.

---

## Web app (current baseline)


| Piece          | Choice                 | Notes                                                                           |
| -------------- | ---------------------- | ------------------------------------------------------------------------------- |
| Backend        | **Laravel**            | App core, HTTP, queues, jobs.                                                   |
| Frontend       | **Inertia.js + React** | SPA-like UX without a separate API client for every page.                       |
| Web auth       | **Laravel Fortify**    | Login, register, password reset, email verification, 2FA — server-driven flows. |
| Routes / types | **Wayfinder**          | Typed routes/helpers for Laravel + TS.                                          |


---

## Database


| Piece      | Choice         | Notes                                                                                                                                                                                                                                                 |
| ---------- | -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Primary DB | **PostgreSQL** | **ACID**, strong **constraints** and transactions (important for money). **JSONB** for flexible audit/metadata without abandoning SQL. First-class **Laravel** support (`pgsql` driver). Huge hosting options (RDS, Cloud SQL, Neon, Supabase, etc.). |


**Why it fits Budera:** ledger-style data, referential integrity, complex queries later (reporting, reconciliation), and room to grow (extensions, partitioning) without leaving the ecosystem. **MySQL/MariaDB** are also fine for many Laravel apps; Postgres is a common pick when you want **Postgres-specific** features and a lot of fintech-adjacent tooling assumes SQL + strong consistency.

### Encryption at rest


| Layer                                  | Who does it                                                             | Notes                                                                                                                                                                                                                                           |
| -------------------------------------- | ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Postgres + disks**                   | **Cloud / host** (RDS, Cloud SQL, Neon, Supabase, Fly.io volumes, etc.) | Enable **storage encryption** for the instance and **encrypted backups/snapshots** — this is the main “encryption at rest” story for the database. **Laravel does not encrypt the whole DB**; the provider encrypts volumes or managed storage. |
| **Secrets in the app**                 | **Laravel**                                                             | `.env` / vault for `APP_KEY`, DB passwords, API keys — never committed.                                                                                                                                                                         |
| **Extra-sensitive columns** (optional) | **Laravel**                                                             | `encrypted` casts / `Crypt::encryptString` for specific fields (tokens, national IDs) — **field-level** encryption on top of disk encryption; key rotation is your process.                                                                     |
| **Object storage** (exports, receipts) | **S3 / GCS / R2**                                                       | Server-side encryption (SSE-S3, SSE-KMS) on buckets.                                                                                                                                                                                            |


**In practice:** turn on **managed DB encryption + encrypted backups** where you host Postgres; use **TLS in transit** to the DB (`sslmode=require`); add **column encryption** only where policy or threat model requires it (more ops overhead).

---

## RBAC (roles & permissions)


| Piece   | Choice                        | Notes                                                                                           |
| ------- | ----------------------------- | ----------------------------------------------------------------------------------------------- |
| Package | **spatie/laravel-permission** | Roles + permissions, cache-friendly, middleware, Blade/Inertia-friendly.                        |
| Model   | **Users** + roles/permissions | Map org/tenant scoping in **policies** (or Spatie teams) — RBAC is not multi-tenancy by itself. |


### Admin vs company (different access worlds)

**Budera internal** and **AI company** staff sit in different trust boundaries: **global ops** vs **tenant-scoped** org data. **End users**, **bank partners**, and **agents** are separate again. Policies must never blur these.

**Define permissions and UI gates from the core role names below before building screens** — retrofitting later is expensive and risky.

### Core role names (canonical — seed + enforce before any UI)

These **snake_case names** are what we register in **Spatie** (where a **human `User`** exists) or enforce via **API principals** (for non-humans). One table = product + RBAC alignment.


| Role name               | Who                               | Responsibility                                                                                                    | Typical surface                                                                                  |
| ----------------------- | --------------------------------- | ----------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `**budera_admin`**      | **Budera team** (CEO / CTO / ops) | Strategy, **live-key approvals**, risk, incidents, global config — internal admin.                                | Internal / exec portal                                                                           |
| `**company_owner`**     | **AI company**                    | Registers org, **integrates the API**, sets **policies**, billing, members, go-live — full control within tenant. | Company dashboard                                                                                |
| `**company_developer`** | **AI company**                    | Day-to-day **integration**: keys (within policy), webhooks, logs — narrower than owner.                           | Company dashboard                                                                                |
| `**end_user`**          | **Human end user**                | **Authorizes** agents, **links bank**, **spending limits**, consent.                                              | End-user app (separate product from company UI)                                                  |
| `**bank_partner`**      | **Sponsor bank / portal user**    | **KYB** approval, **compliance**, sponsor-bank **reporting** — partner ops.                                       | Bank portal (separate auth & boundary)                                                           |
| `**agent`**             | **Non-human principal**           | **Initiates payments**, receives funds, **wallet movements** per policy — programmable actor.                     | **API only** (Sanctum token / agent credential); **not** a dashboard login; audit via domain log |


**Spatie vs principals:** assign `**budera_admin`**, `**company_***`, `**end_user**`, `**bank_partner**` to `**users**` (or split internal/bank into separate user tables — schema TBD) with **teams** / `organization_id` where tenant-scoped. `**agent`** is **not** a person: implement as **token abilities + org scope** (and optional `agent` label on the credential row) — treat as a **first-class role name** in docs and policies even if it never appears on a `User` Spatie pivot.

**Design rule:** one screen/API path per role row — no mixing end-user flows into the company dashboard or bank data without explicit guards.

---

## HTTP API (SDKs, agents, mobile)


| Piece      | Choice                             | Notes                                                                         |
| ---------- | ---------------------------------- | ----------------------------------------------------------------------------- |
| Auth       | **Laravel Sanctum**                | First-party API tokens, token abilities, good fit for **your** APIs and SDKs. |
| JSON       | **API Resources** (`JsonResource`) | Stable response shapes; version by resource class when needed.                |
| Validation | **Form requests**                  | Per-endpoint rules.                                                           |
| Versioning | **Route prefix** (e.g. `/api/v1`)  | No extra package; `Route::prefix('v1')->group(...)`.                          |


### API documentation (developer product)


| Piece      | Choice                                                                   | Notes                                                                                                                                                                                                                                                                                                    |
| ---------- | ------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Approach   | **Hand-maintained** — **not** Scribe (or other route-to-docs generators) | Docs stay **intentional**: quickstart, auth, `/api/v1` reference, errors, webhooks, idempotency. Ship on whatever portal we choose (e.g. **Mintlify**, MDX, or static site). Optional **OpenAPI** later if we want a spec file — **written or exported manually**, not generated from Laravel by Scribe. |
| Discipline | **PR / release checklist**                                               | Public API changes **touch the docs** the same way they touch tests.                                                                                                                                                                                                                                     |


### Sandbox vs live (single Budera domain)

We **do not** use separate hostnames or second deployments for sandbox (e.g. `sandbox-api.` vs `api.`). One **main Budera platform**; mode is enforced by **organization + API credential**, not by DNS.


| Rule                           | Behavior                                                                                                 |
| ------------------------------ | -------------------------------------------------------------------------------------------------------- |
| **New developer / AI company** | Onboards on the main app; **sandbox is the default** for API access.                                     |
| **Creating API keys**          | **Sandbox keys** — always allowed, **no approval**; use for build and test (Stripe-like **test mode**).  |
| **Live / production keys**     | **Not** self-serve at first — **approval** (risk, KYC, contract) after the org requests **live** access. |
| **Dashboard**                  | **Test mode** banner while the org is sandbox-only (or equivalent UX) — same idea as Stripe.             |



| Engineering                                                                                                                                   | Requirement |
| --------------------------------------------------------------------------------------------------------------------------------------------- | ----------- |
| **Every API key** (Sanctum token row or equivalent) stores `**environment`: `sandbox` | `live`**.                                             |             |
| **Every API request** resolves org + key → **environment**; all money-adjacent queries are scoped with **org + environment** (no cross-leak). |             |
| **Queues / jobs / webhooks** carry **environment** (or org + key context) so async work never mixes sandbox and live.                         |             |
| **Live issuance** is gated by **org approval state** (e.g. `live_enabled_at`, internal flag), not only by a UI toggle.                        |             |


**Docs:** public examples use **sandbox** keys until the org is approved for live; document the **live key request / approval** flow.

---

## OAuth2 (third-party integrations)


| Piece           | Choice               | Notes                                                                                                                    |
| --------------- | -------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Package         | **Laravel Passport** | Full **OAuth2** server (clients, tokens, refresh flows) when **external apps** must authorize users or connect accounts. |
| When not to use | —                    | First-party **only** API keys / server tokens → **Sanctum** is enough; Passport adds ops complexity.                     |


**Rule of thumb:** **Sanctum** = our product and our SDKs. **Passport** = partners who need standard OAuth2.

---

## Observability (errors, performance, incidents)


| Piece                           | Choice                 | Notes                                                                                                                    |
| ------------------------------- | ---------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| APM / errors / Laravel insights | **Laravel Nightwatch** | First-party Laravel observability (exceptions, slow queries, jobs, requests). **Chosen** for deep framework integration. |


**Nightwatch vs Sentry (and similar):** not a universal “winner.” **Sentry** is excellent, language-agnostic, huge ecosystem. **Nightwatch** fits **Laravel-first** teams who want official, framework-aware tracing and ops UX in one place. Either can sit beside domain logging; we standardize on **Nightwatch** for app/ops visibility.

**Separate from** domain audit (below): Nightwatch = **what broke / was slow**; audit = **what actor did what** in business terms.

---

## Transactional email

Product email is **not** only Fortify (verify, reset). We need reliable **transactional** delivery for lifecycle and money-adjacent events.


| Piece               | Choice                                                                    | Notes                                                                                                                                                                        |
| ------------------- | ------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Delivery            | **Laravel Notifications** + **Mail** (or `Mail::send`)                    | Queued by default for notifications; keep templates versioned; test with fakes in CI.                                                                                        |
| Provider (SMTP/API) | **Decide early** among **Postmark**, **Resend**, **Mailgun** (or similar) | **Pick before** hardening sandbox flows — each has different **sandbox/test inboxes**, webhooks, and bounce handling; switching later is doable but wastes integration time. |



| Typical triggers       | Examples                                                                     |
| ---------------------- | ---------------------------------------------------------------------------- |
| KYC / identity         | KYC **completed**, **needs info**, verification failed                       |
| Approvals              | **Live API key** / go-live **approval request** (internal + customer-facing) |
| Funding / verification | **Micro-deposit** instructions, ACH verify reminders                         |
| Risk & ops             | **Account alerts** (suspicious activity, limit hit, low balance)             |


**Sandbox:** use provider **test mode** / non-prod keys in non-production envs; never send real customer mail from dev unless using a safe sink (Mailpit, provider test recipients).

---

## Domain events & audit (agent, developer, bank)

**Not** application error tracking. **Structured business events** with a **single place** in code that records them so nothing important is logged ad hoc.


| Idea                          | Detail                                                                                                                                                                                                                                                                 |
| ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Centralized log service**   | One API (e.g. `DomainLog::record(...)` / `AuditService`) used everywhere: controllers, jobs, webhooks.                                                                                                                                                                 |
| **Route by causer / context** | **Developer / org (human) actions** → **developer log** (dashboard, API key changes, integration settings). **Agent + bank / money path** (payments, limits, funding, provider outcomes) → **separate audit store** (longer retention, stricter access, export-ready). |
| **Why it’s strong**           | **One entry point** → consistent shape (actor, action, resource ids, correlation id); **classification** keeps compliance/support queries out of dev noise; **both streams** still get every classified event.                                                         |


Implementation detail (TBD): two tables, two log channels, or one table with `stream` / `realm` — pick when we model schemas. **Raw exceptions** stay in **Nightwatch**, not in audit payloads.

---

## State machines (payments, accounts, top-ups, KYC, …)


| Piece   | Choice                          | Notes                                                                                                                                    |
| ------- | ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| Package | **spatie/laravel-model-states** | **Chosen.** Explicit **states** and **allowed transitions** (class-based); aligns with **Laravel 13+** and the rest of our Spatie stack. |


**Why not ad-hoc `if ($status === 'pending')`:** invalid transitions become bugs and support incidents; the package **refuses** illegal moves.

**Why not `asantibanez/laravel-eloquent-state-machines`:** upstream **Laravel 12+** support has lagged (open issues / forks); we avoid that operational risk.

**Logging `pending` → … →** : the package does **not** replace **Domain events & audit** — on each successful transition, call the **centralized log service** (and/or a `state_transitions` table) so **every** lifecycle change is auditable with **from**, **to**, **actor**, **correlation id**.

---

## Webhooks

Two different problems: **incoming** (providers → Budera) vs **outgoing** (Budera → customers).


| Direction    | What it is                                                                  | Approach                                                                                                                                                                                                |
| ------------ | --------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Incoming** | Banks, card networks, Plaid, Stripe, etc. POST events to **our** URL        | `POST` route → **verify signature** (provider SDK or HMAC) → **dispatch a queued job** → **200** quickly. Optional: **spatie/laravel-webhook-client** if we want a uniform pipeline for many providers. |
| **Outgoing** | Budera notifies **customer** servers (`payment.settled`, `limit.hit`, etc.) | **spatie/laravel-webhook-server** — signed delivery, retries; our domain events feed Spatie’s dispatch.                                                                                                 |



| Piece                           | Choice                                                                  | Notes                                                                                                        |
| ------------------------------- | ----------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Outgoing (Budera → subscribers) | **spatie/laravel-webhook-server**                                       | **Chosen.** Handles signing + sending + retry semantics; align payload builders with audit/compliance needs. |
| Incoming (providers → Budera)   | **Routes + queues + jobs** (optional **spatie/laravel-webhook-client**) | Verify every payload; idempotent handlers.                                                                   |
| Provider SDKs                   | **Per integration**                                                     | e.g. Stripe signing helpers — follow vendor docs.                                                            |


**Fintech hygiene:** treat incoming webhooks as **untrusted** until verified; log for audit; **idempotent** handlers (same event id → single side effect).

### Spatie `laravel-webhook-server` — Postcardware

The package is **Postcardware**: free to use; if it ships to **production**, Spatie asks that you send them a **postcard from your hometown**, mentioning which package(s) you use.

**Address:** Spatie, Kruikstraat 22, 2018 Antwerp, Belgium.

They publish received postcards on [their company website](https://spatie.be/).

---

## API versioning & sandbox (how we deploy)

| Topic | What we do |
|--------|------------|
| **API versions** | **URL prefix** + route groups (e.g. `/api/v1`); optional split files like `routes/api/v1.php` as the API grows. |
| **Sandbox vs live** | **One** Budera app and **one** main hostname — we **do** isolate sandbox from live **in data and auth** (sandbox vs live **API keys**, **org**, **scoped rows**). We are **not** using a **second domain** or a **separate deployment** *only* for sandbox; see **HTTP API → Sandbox vs live**. |

---

## Optional / later (document when adopted)


| Topic                              | Examples                                                                                         |
| ---------------------------------- | ------------------------------------------------------------------------------------------------ |
| Queue / app metrics                | Laravel Pulse, Horizon (if not fully covered by Nightwatch)                                      |
| Payments / compliance              | Provider-specific SDKs; domain audit via centralized **log service** (see Domain events & audit) |
| Incoming webhooks (many providers) | Optional: `spatie/laravel-webhook-client`                                                        |


---

## Install checklist (when you wire each piece)

- `spatie/laravel-permission` — publish config/migrations; seed core roles `**budera_admin`**, `**company_owner**`, `**company_developer**`, `**end_user**`, `**bank_partner**` + `**agent**` principal model; permission matrix before building any actor UI.
- **Laravel Nightwatch** — connect app, dashboards, alerts; keep **separate** from domain audit streams.
- **Transactional email** — choose provider (**Postmark** / **Resend** / **Mailgun** or equivalent); `config/mail.php`, queue notifications, templates for KYC, approvals, micro-deposits, alerts; sandbox vs prod keys.
- **Centralized domain log service** — causer-based routing (developer vs agent+bank); migrations + policies for retention/export.
- `spatie/laravel-model-states` — state classes + transitions for core lifecycles; **hook transitions** into domain audit logging.
- **API docs (v1)** — hand-maintained developer docs + release/PR discipline; **no Scribe** (see HTTP API section).
- **Sandbox vs live** — `environment` on API keys; middleware + query scopes; org **approval** for first **live** keys; dashboard **test mode** banner; jobs/webhooks carry environment.
- `laravel/sanctum` — `HasApiTokens`, `config/sanctum.php`, migrate, protect `routes/api.php`.
- `laravel/passport` — only if OAuth2 for third parties is in scope; install keys, migrations, Passport::routes.
- `spatie/laravel-webhook-server` — outgoing webhooks to customers; configure signing, queues; **send Spatie a postcard if we ship it to prod** (see Webhooks section).
- `spatie/laravel-webhook-client` — optional, for incoming provider webhooks if we want the unified pipeline.

---

**See also:** [`budera-dev-timeline.md`](./budera-dev-timeline.md) — **design-first plan (01–18)**: ERD → RBAC matrix → tenancy → OAuth → Column mock → state machines → API contract → spend pipeline → ledger → KYC/KYB → bank link → webhooks → audit → idempotency → UI → tests → email → infra.

---

*Last updated: stack intent doc — align with `composer.json` as packages are added.*