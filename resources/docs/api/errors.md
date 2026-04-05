# Errors

All non-validation error responses follow a consistent JSON envelope:

```json
{
  "error": {
    "code": "payment_not_found",
    "message": "Human-readable summary.",
    "detail": null,
    "layer": "not_found"
  }
}
```

## Error envelope fields

| Field | Description |
|-------|-------------|
| `code` | Stable machine identifier. Use this for programmatic error handling. See `config/api_errors.php` for the full catalog. |
| `message` | Human-readable description. Safe to show to end users, but do not rely on exact wording for logic. |
| `detail` | Optional string or structured object with additional context (e.g. validation hints). May be `null`. |
| `layer` | Rough category of the error. Helps with triage and logging. |

## Error layers

| Layer | Description |
|-------|-------------|
| `auth` | Authentication or authorization failure |
| `validation` | Request validation failure |
| `idempotency` | Idempotency key conflict or missing header |
| `sandbox` | Sandbox-specific error (e.g. using live key on simulation endpoint) |
| `policy` | Spend control policy rejection |
| `not_found` | Resource not found |
| `webhook` | Webhook-related error |
| `internal` | Unexpected server error |

## Common error codes

| Code | HTTP | Layer | When it happens |
|------|------|-------|-----------------|
| `unauthenticated_api` | 401 | auth | Missing or invalid Bearer token |
| `missing_api_key_ability` | 403 | auth | API key lacks required ability/scope |
| `missing_token_scope` | 403 | auth | OAuth token lacks required scope |
| `payment_not_found` | 404 | not_found | Payment ID doesn't exist or is out of scope |
| `wallet_not_found` | 404 | not_found | Wallet account not found |
| `insufficient_balance` | 422 | policy | Wallet balance too low for the operation |
| `policy_rejected` | 422 | policy | Payment rejected by spend controls |
| `rate_limit_exceeded` | 429 | auth | Too many requests; check `Retry-After` header |
| `idempotency_key_conflict` | 409 | idempotency | Same key used with different request body |
| `sandbox_only` | 403 | sandbox | Simulation endpoint called with a live key |
| `webhook_not_configured` | 503 | webhook | Inbound webhook secret not configured |
| `invalid_signature` | 401 | webhook | HMAC signature verification failed |

## Validation errors (HTTP 422)

Laravel form request validation returns the standard format:

```json
{
  "message": "The amount cents field is required.",
  "errors": {
    "amount_cents": ["The amount cents field is required."]
  }
}
```

Field-level errors are keyed by the input field name. This is in addition to any route-specific error envelope.

## Rate limiting (HTTP 429)

When rate limited, the response includes:
- `Retry-After` header with the number of seconds to wait
- Error code `rate_limit_exceeded`

> **Tip:** Implement exponential backoff in your agent's retry logic. Start with the `Retry-After` value, then increase delay on subsequent retries.
