# Idempotency

Budera enforces idempotency on all mutating endpoints that create money movement. This ensures that retries (from network timeouts, agent retries, etc.) don't produce duplicate transactions.

## Which endpoints require it?

All `POST` requests that create side effects:

- **Payments** — `POST /api/v1/payments`
- **Top-ups** — `POST /api/v1/topups`
- **Transfers** — `POST /api/v1/transfers`

## How it works

Send an `Idempotency-Key` header with a unique value (UUID recommended):

```bash
curl -X POST \
  -H "Authorization: Bearer $BUDERA_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{"wallet_account_id": "wal_xxx", "amount_cents": 2500}' \
  https://app.budera.com/api/v1/payments
```

## Rules

1. **Required:** The header must be present and non-empty. Maximum length is 255 characters.
2. **Scoped to company:** Each idempotency key is unique per company (derived from your API key or OAuth token).
3. **Replay on match:** If the same key is sent with the **same request fingerprint** (method, path, route params, JSON body), Budera **replays** the stored HTTP status and JSON body from the first successful (2xx) response.
4. **Conflict on mismatch:** If the same key is sent with a **different request fingerprint**, Budera returns **HTTP 409** with code `IDEMPOTENCY_KEY_CONFLICT`.

## Best practices

> **Tip:** Generate a new UUID for each logical operation your agent attempts. If the same operation needs to be retried (e.g. network timeout), reuse the same UUID.

> **Tip:** Don't reuse idempotency keys across different operations. For example, don't use the same key for a payment and a top-up.

> **Tip:** Store the idempotency key alongside your operation record so you can correlate retries with original requests in your logs.

## Error handling

```json
{
  "error": {
    "code": "idempotency_key_conflict",
    "message": "The idempotency key was already used with a different request.",
    "detail": null,
    "layer": "idempotency"
  }
}
```

If you receive a 409 conflict, it means the key was already used with a different request body. Generate a new key and retry.

A missing `Idempotency-Key` header on a required route returns:

```json
{
  "error": {
    "code": "idempotency_key_missing",
    "message": "An Idempotency-Key header is required for this request.",
    "detail": null,
    "layer": "idempotency"
  }
}
```
