# API Overview

Budera is **the bank account for AI agents** — a persistent, programmable financial identity at the API layer so non-human principals (agents) can hold, receive, and move money under rules humans approve.

## How it works

```
AI Company registers → Integrates API/MCP → Sandbox testing → KYB review → Live access
→ End users authorize agents via OAuth → Wallet created → Bank account provisioned
→ Agents act on behalf of users through the Budera API
```

1. **AI companies** sign up and register on Budera.
2. They integrate Budera's API (or MCP server / tools layer) into their AI product — giving their agents the ability to interact with financial infrastructure.
3. In **sandbox mode**, everything is simulated. Companies test agent workflows, webhook delivery, and spend controls without real money.
4. When ready, companies **submit for KYB review**. Budera approves the company for production access.
5. **End users** of the AI company authorize the agent via OAuth. This creates a **Budera wallet** for the end user — with a real (or mock, in sandbox) bank account attached.
6. The AI agent acts on behalf of the user: making payments, top-ups, transfers — all governed by **spend controls**, **policies**, and **audit logging**.

## Base URL

All API endpoints live under:

```
https://<your-app-domain>/api/v1
```

## Request format

- All request bodies use `Content-Type: application/json`.
- Responses are JSON.
- Mutating operations (`POST` payments, transfers, top-ups) require an `Idempotency-Key` header.

## Authentication

Two authentication methods are supported:

- **API keys** (recommended for agent integrations) — Bearer token in the `Authorization` header.
- **OAuth2 access tokens** (Passport) — for human-facing authorization flows.

See the [Authentication](/docs/authentication) page for details.

> **Tip:** Start with sandbox API keys. You can create them from **Company → API Keys** in the dashboard. Live keys are only available after KYB approval.

## Environments

Every API key and webhook endpoint is scoped to an **environment**:

- **Sandbox** — simulated data, mock bank, no real money. Use `sandbox:simulate` ability for simulation endpoints.
- **Live** — real bank accounts, real money movement. Requires KYB approval and live access grant.

> **Tip:** Keep sandbox and live API keys in separate environment variables. Never use live keys in development or CI.
