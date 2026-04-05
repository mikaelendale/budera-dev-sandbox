# Authentication

Budera supports two authentication methods: **API keys** for agent/server integrations, and **OAuth2** for user-facing authorization.

## API keys

Create keys from **Company → API Keys** in the dashboard. Each key is tied to a company and an environment (sandbox or live).

```http
Authorization: Bearer budera_sandbox_sk_abc123...
```

- Only a **SHA-256 hash** of the key is stored — the raw key is shown **once** at creation. Copy it immediately.
- Keys carry **abilities** (scopes): `wallet:read`, `wallet:pay`, `wallet:link`, `wallet:topup`, `wallet:transfer`, `sandbox:simulate`.
- Routes enforce the required ability via middleware. A 403 response with code `missing_api_key_ability` means your key lacks the required scope.

### Rotating keys

Use the **Rotate** button in the dashboard. This:
1. Marks the current key as `rotated` (no longer usable).
2. Creates a new active key with the same abilities and environment.
3. Shows the new raw key **once**.

> **Tip:** Only `active` keys can be rotated. Revoked or already-rotated keys cannot be rotated again.

### Live keys

Live API keys are **unavailable** until your company completes KYB review and is approved for production access. The dashboard enforces this — attempting to create a live key before approval returns an error.

## OAuth2 (Passport)

End users authorize AI agents via the OAuth2 authorization code flow (with PKCE). This yields access tokens checked against the `api` guard.

1. Register an **OAuth client** in **Company → OAuth Apps**.
2. Direct users to Budera's authorization endpoint.
3. Exchange the authorization code for an access token.
4. Use the token as a Bearer token — scopes map to the same ability names (`wallet:pay`, etc.).

> **Tip:** Use public clients with PKCE for single-page apps and native mobile apps. Confidential clients are for server-to-server flows.

## Error responses

**Missing or invalid authentication:**

```json
{
  "error": {
    "code": "unauthenticated_api",
    "message": "Authentication is required.",
    "detail": null,
    "layer": "auth"
  }
}
```

**Missing scope/ability (HTTP 403):**

```json
{
  "error": {
    "code": "missing_api_key_ability",
    "message": "Your API key does not have the required ability.",
    "detail": null,
    "layer": "auth"
  }
}
```
