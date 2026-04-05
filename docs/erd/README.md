# Budera — core ERD (Mermaid)

Design-first artifacts for **section 01 — Full ERD** in [`docs/budera-dev-timeline.md`](../budera-dev-timeline.md#01--full-erd). **Not** migrations — schema is the source of truth *before* `001` migration.

## How to view

| Tool | What to open |
|------|----------------|
| **GitHub** | Open any `*.md` file below; diagrams render from fenced `mermaid` code blocks. |
| **VS Code** | Mermaid preview extension; open the same `*.md` files. |
| **[Mermaid Live Editor](https://mermaid.live)** | Copy only the diagram source inside the opening `mermaid` fence through the closing fence to validate or export SVG/PNG. |

**Layout tip:** Large diagrams may clip in GitHub; use **sliced** files (`02`–`08`) for full column detail. Use **`99-full-graph-er.md`** for a single canvas with all entities (minimal attributes).

## File index

| File | Contents |
|------|----------|
| [`00-legend-and-conventions.md`](./00-legend-and-conventions.md) | Notation, streams, polymorphic rules. |
| [`01-overview-er.md`](./01-overview-er.md) | Bird’s-eye: PK/FK fields + relationships only. |
| [`02-tenancy-and-principals-er.md`](./02-tenancy-and-principals-er.md) | `companies`, `api_keys`, `users`. |
| [`03-wallets-and-policies-er.md`](./03-wallets-and-policies-er.md) | `accounts`, `policies`. |
| [`04-funding-and-rails-er.md`](./04-funding-and-rails-er.md) | `bank_links`, `topups`. |
| [`05-money-movements-er.md`](./05-money-movements-er.md) | `payments`, `transfers`, `ledger_entries`. |
| [`06-api-reliability-er.md`](./06-api-reliability-er.md) | `idempotency_keys`. |
| [`07-webhooks-er.md`](./07-webhooks-er.md) | `webhook_endpoints`, `webhook_deliveries`. |
| [`08-observability-and-compliance-er.md`](./08-observability-and-compliance-er.md) | `domain_audit_log`, `state_transitions`, KYC/KYB/compliance stubs. |
| [`99-full-graph-er.md`](./99-full-graph-er.md) | Full entity graph, minimal attributes. |

## Stub compliance tables (full columns in `08`)

| Table | Role |
|-------|------|
| `kyc_sessions` | Hosted KYC vendor session per `users` row; status + provider refs. |
| `kyb_reviews` | KYB queue row per `companies`; optional `reviewer_user_id` → `users` (e.g. `budera_admin`). |
| `compliance_flags` | Polymorphic open items (`subject_type` / `subject_id`) for OFAC, structuring, manual review. |
| `approval_requests` | Human-in-the-loop holds for spend controls; optional 1:1 `payment_id`. |

## Assumptions (explicit)

1. **`webhook_events` → `webhook_deliveries`:** The timeline lists “webhook_endpoints, webhook_events” with a delivery log. We model **outbound delivery attempts** as `webhook_deliveries` (one row per attempt or per logical event dispatch; see [`07-webhooks-er.md`](./07-webhooks-er.md)).
2. **`agents` table:** Not in the starting list. `accounts.agent_id` is an **opaque string** (e.g. external id from the AI company) until `agents` is added.
3. **`users.company_id`:** Present per starting list. If product later separates **AI company staff** from **end users**, expect new tables (e.g. `end_user_profiles`) — this ERD stays aligned with the **starting list**.

## Global enums (logical)

Values below are **logical**; Postgres may use `CHECK`, `ENUM` types, or lookup tables.

### `environment`

`sandbox` | `live`

### `kyb_status` (on `companies`)

`pending` | `under_review` | `approved` | `rejected`

### `kyc_status` (on `users`)

`not_started` | `pending` | `approved` | `rejected` | `needs_info`

### `api_key_environment` (on `api_keys`)

Same as `environment`.

### `bank_link_status`

`initiated` | `microdeposit_sent` | `verified` | `failed` | `revoked`

### `account_status`

`pending` | `active` | `paused` | `frozen` | `closed`

### `payment_status`

`pending` | `approved` | `processing` | `settled` | `failed` | `returned` | `held_anomaly` | `held_approval`

### `payment_direction`

`outbound` | `inbound`

### `payment_rail`

`ach` | `rtp` | `wire` | `card` | `internal`

### `topup_status` / `transfer_status`

`pending` | `processing` | `settled` | `completed` | `failed` | `returned` (topup only where applicable)

### `ledger_entry_type`

`debit` | `credit`

### `audit_stream`

`developer` | `agent_bank`

### `webhook_delivery_status`

`pending` | `delivered` | `failed` | `retrying`

## Relationship matrix (authoritative)

Parent → Child cardinality and FK. Use this if a diagram truncates.

| Parent | Child | Cardinality | FK / notes |
|--------|-------|-------------|------------|
| `companies` | `api_keys` | 1..N | `api_keys.company_id` |
| `companies` | `users` | 0..N | `users.company_id` nullable if end-users without org — **v1 assumes nullable OK** |
| `companies` | `accounts` | 1..N | `accounts.company_id` |
| `companies` | `idempotency_keys` | 1..N | `idempotency_keys.company_id` |
| `companies` | `webhook_endpoints` | 0..N | `webhook_endpoints.company_id` |
| `companies` | `kyb_reviews` | 0..N | `kyb_reviews.company_id` |
| `users` | `kyb_reviews` | 0..N | `kyb_reviews.reviewer_user_id` (nullable; Budera reviewer) |
| `users` | `bank_links` | 0..N | `bank_links.user_id` |
| `users` | `accounts` | 0..N | `accounts.user_id` (wallet beneficial owner / link) |
| `users` | `kyc_sessions` | 0..N | `kyc_sessions.user_id` |
| `accounts` | `policies` | 1..1 | `policies.account_id` UNIQUE |
| `accounts` | `payments` | 0..N | `payments.account_id` |
| `accounts` | `topups` | 0..N | `topups.account_id` |
| `accounts` | `ledger_entries` | 0..N | `ledger_entries.account_id` |
| `accounts` | `transfers` | 0..N | `from_account_id`, `to_account_id` |
| `accounts` | `approval_requests` | 0..N | `approval_requests.account_id` |
| `bank_links` | `topups` | 0..N | `topups.bank_link_id` |
| `webhook_endpoints` | `webhook_deliveries` | 0..N | `webhook_deliveries.webhook_endpoint_id` |
| `payments` | `approval_requests` | 0..1 | `approval_requests.payment_id` UNIQUE nullable |

**Polymorphic / generic**

| Table | Points to |
|-------|-----------|
| `ledger_entries` | `reference_type` + `reference_id` → `payments`, `topups`, `transfers`, fees, etc. |
| `state_transitions` | `model_type` + `model_id` → any stateful domain model |
| `compliance_flags` | `subject_type` + `subject_id` → `users`, `accounts`, `payments`, … |
| `domain_audit_log` | `resource_type` + `resource_id` (optional); always has `actor_*` |

## Indexes and constraints (not in Mermaid)

| Table | Constraint / index |
|-------|---------------------|
| `api_keys` | UNIQUE(`key_hash`) where not revoked; partial index common. |
| `idempotency_keys` | UNIQUE(`company_id`, `key`); TTL enforced by app or scheduled cleanup. |
| `accounts` | INDEX(`company_id`, `environment`); INDEX(`user_id`). |
| `payments` | INDEX(`account_id`, `status`, `created_at`). |
| `ledger_entries` | INDEX(`account_id`, `created_at`); INDEX(`reference_type`, `reference_id`). |
| `domain_audit_log` | INDEX(`correlation_id`); INDEX(`resource_type`, `resource_id`); BRIN/GIN on `metadata` as needed later. |

## Column dictionary (selected cross-cutting)

| Column | Meaning |
|--------|---------|
| `live_enabled_at` | When Budera **approved** live API access for the company (`NULL` = sandbox-only). |
| `sandbox_limit_overrides` | JSONB caps for sandbox testing (max tx, max daily, etc.). |
| `abilities` / `scopes` | JSON array or CSV of token abilities (`wallet:pay`, …). |
| `balance_usd` | **Cached** — must reconcile to `ledger_entries` in same transaction as writes. |
| `routing_hash` | Hone bank routing/account identity for dedupe without storing raw routing in logs. |
| `payee_ref` | Opaque vendor identifier, mask, or tokenized payee id — not raw PAN. |

---

*Version: design artifact; update when section 01 ERD is locked or migrations are generated.*
