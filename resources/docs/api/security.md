# Security

Budera is designed with money safety, tenant isolation, and auditability as core principles.

## Tenant isolation

All data is scoped by **company**. API keys and OAuth tokens resolve a company context, and Eloquent **global scopes** automatically filter all database queries. This ensures that:

- Company A can never access Company B's data via the API
- Wallet accounts, payments, and bank links are always scoped
- Even admin operations go through explicit audit paths

> **Tip:** Never attempt to bypass company scoping in custom integrations. If you need cross-company access, contact Budera support.

## Sandbox vs live environments

API keys carry an **environment** (`sandbox` or `live`). The system enforces strict separation:

- Sandbox simulation routes **reject** live keys (HTTP 403, `sandbox_only`)
- Live endpoints **reject** sandbox keys
- Dashboard environment toggle lets you switch between viewing sandbox and live data
- Webhook endpoints are scoped to an environment

> **Tip:** Use separate environment variables for sandbox and live keys. Never deploy sandbox keys to production or vice versa.

## Spend controls pipeline

Every payment passes through a multi-stage spend controls pipeline before execution:

1. **Policy gate** — checks per-transaction limits, daily limits, allowed categories, blocked payees
2. **Balance gate** — verifies sufficient wallet balance
3. **Velocity engine** — detects anomalous spending patterns based on configurable sensitivity (low/medium/high)
4. **Approval gate** — holds payments exceeding the `require_approval_above` threshold for human approval
5. **Compliance screen** — runs additional compliance checks

If any gate rejects, the payment is either denied or held for approval. All decisions are logged in the **authorization ledger**.

> **Tip:** Configure spend policies per wallet from the dashboard. Start with conservative limits and adjust based on your use case.

## Secrets at rest

Budera never stores sensitive credentials in plaintext:

| Data | Storage method |
|------|---------------|
| API keys | **SHA-256 hash** only (`key_hash`). The raw key is shown once at creation. |
| Webhook endpoint secrets | **Encrypted** at rest using Laravel's encryption |
| Bank link credentials | **Encrypted** fields (`encrypted_routing`, `encrypted_account`) |
| Partner bank integration credentials | **Encrypted** array |

## Audit logging

All state transitions and money movements are recorded:

- **Domain audit log** — every significant action with actor, timestamp, IP, and correlation ID
- **Authorization ledger** — cryptographically signed record of all spend control decisions
- **State transitions** — full history of every model state change

> **Tip:** Use the `X-Correlation-Id` header in your API requests to trace operations through the audit log.

## Web vs API authentication

- **Web routes** (Inertia dashboard) use the default web middleware stack including CSRF protection on state-changing requests.
- **API routes** are **stateless** — use Bearer tokens, not session cookies. No CSRF tokens needed.

## CORS

If browser clients call the API cross-origin, configure allowed origins in `config/cors.php` for `api/*` paths.

## Rate limiting

Per-company rate limits apply on `/api/v1` endpoints. When rate limited:

- Response status: **HTTP 429**
- `Retry-After` header indicates seconds to wait
- Error code: `rate_limit_exceeded`

> **Tip:** Implement exponential backoff in your agent's HTTP client. Respect the `Retry-After` header and add jitter to prevent thundering herd effects.
