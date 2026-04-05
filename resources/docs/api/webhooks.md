# Webhooks

Budera delivers signed JSON events to your HTTPS endpoints when things happen in the system — payments settle, KYC completes, accounts change state, etc.

## Setting up webhooks

1. Go to **Company → Webhooks** in the dashboard.
2. Add an HTTPS endpoint URL.
3. Select which events to subscribe to (or `*` for all).
4. Copy the **signing secret** shown once at creation. Store it securely.

## Event catalog

Events are namespaced by resource type:

| Event | When it fires |
|-------|---------------|
| `account.active` | Wallet account becomes active |
| `account.frozen` | Wallet account is frozen |
| `account.unfrozen` | Wallet account is unfrozen |
| `kyc.approved` | KYC verification approved |
| `kyc.failed` | KYC verification failed |
| `kyc.needs_info` | Additional information needed for KYC |
| `kyb.approved` | Company KYB review approved |
| `live.enabled` | Company granted live access |
| `payment.approved` | Payment completed successfully |
| `test.ping` | Manual test ping from the dashboard |

Subscribe to `*` to receive all events. You can update subscriptions at any time from the dashboard.

## Delivery format

Each delivery is a `POST` request to your endpoint with:

```json
{
  "event": "payment.approved",
  "payload": { ... },
  "timestamp": "2026-03-27T12:00:00Z"
}
```

## HMAC signature verification

Every delivery includes a `Signature` header with an HMAC-SHA256 signature:

```
Signature: sha256=<hex_digest>
```

To verify:

1. Compute `HMAC-SHA256(signing_secret, raw_request_body)`.
2. Compare the hex digest with the value after `sha256=` in the header.
3. Use a **constant-time comparison** to prevent timing attacks.

```python
import hmac
import hashlib

def verify_signature(secret: str, body: bytes, signature_header: str) -> bool:
    expected = "sha256=" + hmac.new(
        secret.encode(), body, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature_header)
```

> **Tip:** Always verify the signature before processing the event. Unverified webhooks could be spoofed.

## Retry behavior

If your endpoint returns a non-2xx status, Budera retries the delivery with exponential backoff. Check the **Recent deliveries** section in the Webhooks dashboard to monitor delivery status.

> **Tip:** Return a `200` response quickly. If you need to do heavy processing, acknowledge the webhook first and process asynchronously.

## Testing webhooks

Use the **Test ping** button on any endpoint in the dashboard. This sends a `test.ping` event to verify your endpoint is reachable and correctly verifying signatures.

## Inbound mock bank webhook

Budera also has an **inbound** webhook at `POST /api/webhooks/mock-bank` for receiving events from the mock bank service (sandbox only). This uses the same HMAC-SHA256 verification pattern with `X-Signature: sha256=<hex>`.
