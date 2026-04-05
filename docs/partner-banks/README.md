# Partner bank integrations (Budera admin)

This folder documents the **admin-only** partner bank configuration: encrypted credentials, mock-bank / Column resolution, and how to try it locally.

## What exists in the app

- **Table:** `partner_bank_integrations` — one row per **`provider` + `environment`** (e.g. `mock_bank` + `sandbox`). No `company_id`; AI companies never see or edit these rows.
- **UI:** Budera admins only — **`/admin/partner-banks`** (nav: **Partner banks** when logged in as admin).
- **Secrets:** Stored as Laravel **`encrypted:array`** on the `credentials` column (outbound API secret, inbound webhook secret). The UI only shows masked previews after save.
- **Runtime:**
  - **`MockBankClient`** reads base URL + outbound secret via **`PartnerBankIntegrationResolver`** for provider **`mock_bank`** (DB-first).
  - **`MockBankWebhookController`** verifies HMAC using the inbound webhook secret from the same resolver (DB-first).
  - **`ColumnBankService`** is bound to **`ColumnBankMock`** (wraps `MockBankClient`) in non-live scenarios; **`ColumnBankClient`** is reserved for live Column when configured.

## Prerequisites

1. **Database migrated** (includes `partner_bank_integrations` and drops the old tenant `bank_connections` table if it was present):

   ```bash
   php artisan migrate
   ```

2. **Budera admin user** — `users.is_budera_admin = true` for the account you use in the browser.  
   Example (tinker):

   ```bash
   php artisan tinker
   ```

   ```php
   $u = \App\Models\User::where('email', 'you@example.com')->first();
   $u->is_budera_admin = true;
   $u->save();
   ```

3. **App running** (Vite + Laravel as you usually do), logged in as that admin.

## Try the admin UI

1. Open **`/admin/partner-banks`**.
2. **Create integration** — example for local mock-bank:
   - **Label:** e.g. `Mock bank (local)`
   - **Provider key:** `mock_bank` (must match what the resolver uses)
   - **Environment:** `sandbox` (non-production app default) or `live` in production
   - **Base URL:** e.g. `http://127.0.0.1:3000` (your mock-bank Next.js app)
   - **Outbound API secret:** optional; if your mock requires `X-Bank-Secret`, set it to match mock-bank config
   - **Inbound webhook secret:** optional; if mock-bank signs webhooks to Laravel, set it to match what the mock uses to sign `X-Signature`
3. Save — you should see masked previews and status flags, not raw secrets.

Non-admin users get **403** on these routes.

## Resolver behavior (quick reference)

| App environment | Default “environment” for lookups | Notes |
|-----------------|-------------------------------------|--------|
| Non-production (`APP_ENV` not `production`) | `sandbox` | `PartnerBankIntegrationResolver::defaultEnvironment()` |
| Production | `live` | Same |

The resolver picks the **active** row **`WHERE provider = ? AND environment = ? AND is_active = 1`**.

If none match, the resolver returns empty values (webhooks will return `503 webhook_not_configured`, and `bank:ping` fails). In `testing`, existing unit tests still rely on config fallback.

## Automated tests (Pest)

```bash
php artisan test tests/Feature/AdminPartnerBanksTest.php
```

Full suite:

```bash
php artisan test
```

## Mock-bank E2E (scripts)

If mock-bank runs on `http://127.0.0.1:3000`, you can still use the repo scripts (e.g. `scripts/run-mock-bank-e2e.cmd` or `scripts/test-mock-bank-e2e.ps1`) to hit the **mock** service directly. That does not exercise the Laravel admin UI; it confirms the Next.js mock app behaves.  

To exercise **Laravel → mock** with DB-backed config, run `php artisan db:seed --force` once so the default `mock_bank/sandbox` integration row is created from `MOCK_BANK_*` env vars, then use any existing feature tests that call **`MockBankClient`** / wallet APIs (see `tests/Feature/MockBankWalletApiTest.php`, `MockBankWebhookTest.php`, etc.).

## Column adapter (`ColumnBankService`)

- **Inject** `App\Contracts\Banking\ColumnBankService` where the payment pipeline should talk “Column-shaped” API.
- In tests and typical sandbox setups, this resolves to **`ColumnBankMock`**, which delegates to **`MockBankClient`**.
- Live **`ColumnBankClient`** is only selected when **`PartnerBankIntegrationResolver::useLiveColumnClient()`** is true (production + active `column` / `live` integration with outbound credentials).

## Security reminders

- **Rotate `APP_KEY`** only with a documented migration path for encrypted columns.
- Prefer **HTTPS** in production for admin sessions.
- **Audit logging** for admin mutations is a follow-up (not in the initial slice).
