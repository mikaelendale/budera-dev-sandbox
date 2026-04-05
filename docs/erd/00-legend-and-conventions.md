# Legend and conventions (Budera core ERD)

This file complements the Mermaid sources in this folder. Read [`README.md`](./README.md) for the full index, relationship matrix, and rendering notes.

## Crow’s-foot notation (Mermaid `erDiagram`)

Mermaid uses abbreviated relationship lines:

| Syntax | Reads as |
|--------|----------|
| `\|\|--o{` | One to zero or many |
| `\|\|--\|{` | One to one or many (rare) |
| `o\|--o{` | Zero or one to zero or many |
| `\|\|--\|\|` | Exactly one to exactly one |

Cardinality in the diagrams is also stated in [`README.md`](./README.md) where it matters for money (e.g. `accounts` ↔ `policies`).

## Primary keys

- Default: **`uuid`** `id` as PK on all domain tables unless noted otherwise.
- **Surrogate keys only** — no natural keys as PK.

## Foreign keys

- Named `*_id` referencing `table.id` unless polymorphic (`*_type` + `*_id`).
- **ON DELETE** behavior is **not** expressible in Mermaid `erDiagram`; treat as **application-defined** (see README matrix).

## `environment` (sandbox vs live)

- **Scope:** `sandbox` and `live` rows **never** mix in the same API request context.
- **Columns:** `api_keys.environment`, `accounts.environment` (and any other money-adjacent parent that must align with the key).
- **Rule:** `accounts.environment` must match the **API key** used to access the wallet.

## Soft delete vs revoke

- **`revoked_at`:** credential or link is invalid from this timestamp; prefer **not** hard-deleting rows that touch audit or compliance.
- **`deleted_at`:** optional future pattern for non-PII rows; **not** in the starting table list.

## Idempotency

- **`idempotency_keys`:** scoped by **`company_id`** (tenant). Same `(company_id, key)` → same cached response within TTL.
- Mutating POSTs require header `Idempotency-Key` mapping to this table.

## Audit streams (`domain_audit_log.stream`)

| Value | Meaning |
|-------|---------|
| `developer` | Company dashboard / human operator actions (keys, webhooks, policy edits). |
| `agent_bank` | Money path: payments, ledger, bank adapter results, compliance holds. |

## State transitions (`state_transitions`)

- **`model_type` + `model_id`:** polymorphic pointer to the **domain aggregate** (e.g. `Payment`, `Account`).
- **`from` / `to`:** string state names matching the **Spatie model state** class names or serialized state.

## Polymorphic ledger references (`ledger_entries`)

- **`reference_type`:** short class or table name (e.g. `payment`, `topup`, `transfer`, `fee`).
- **`reference_id`:** UUID of that row.

**Invariant:** `ledger_entries.account_id` must match the wallet side of the movement for that entry.

## Stub / future modeling

- **`accounts.agent_id`:** string external reference until an `agents` table exists.
- **`users.company_id`:** matches the starting list; a future split between “Budera staff / AI company users” vs “end users only” may introduce additional tables—see [`README.md`](./README.md) assumptions.
