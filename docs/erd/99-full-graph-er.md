# Budera core ERD — full graph (all entities, minimal attributes)

For column-level detail use [`02-tenancy-and-principals-er.md`](./02-tenancy-and-principals-er.md) through [`08-observability-and-compliance-er.md`](./08-observability-and-compliance-er.md).

```mermaid
%% Budera core ERD — full graph (all entities, minimal attributes)
%% For column-level detail use files 02–08.
erDiagram
    companies {
        uuid id PK
    }

    api_keys {
        uuid id PK
        uuid company_id FK
    }

    users {
        uuid id PK
        uuid company_id FK
    }

    bank_links {
        uuid id PK
        uuid user_id FK
    }

    accounts {
        uuid id PK
        uuid company_id FK
        uuid user_id FK
        string environment
    }

    policies {
        uuid id PK
        uuid account_id FK
    }

    payments {
        uuid id PK
        uuid account_id FK
    }

    topups {
        uuid id PK
        uuid account_id FK
        uuid bank_link_id FK
    }

    transfers {
        uuid id PK
        uuid from_account_id FK
        uuid to_account_id FK
    }

    ledger_entries {
        uuid id PK
        uuid account_id FK
    }

    idempotency_keys {
        uuid id PK
        uuid company_id FK
    }

    webhook_endpoints {
        uuid id PK
        uuid company_id FK
    }

    webhook_deliveries {
        uuid id PK
        uuid webhook_endpoint_id FK
    }

    domain_audit_log {
        uuid id PK
    }

    state_transitions {
        uuid id PK
    }

    kyc_sessions {
        uuid id PK
        uuid user_id FK
    }

    kyb_reviews {
        uuid id PK
        uuid company_id FK
        uuid reviewer_user_id FK
    }

    compliance_flags {
        uuid id PK
    }

    approval_requests {
        uuid id PK
        uuid account_id FK
        uuid payment_id FK
    }

    companies ||--o{ api_keys : issues
    companies ||--o{ users : employs
    companies ||--o{ accounts : owns
    companies ||--o{ idempotency_keys : scopes
    companies ||--o{ webhook_endpoints : configures
    companies ||--o{ kyb_reviews : kyb

    users ||--o{ bank_links : links
    users ||--o{ accounts : owns_wallet
    users ||--o{ kyc_sessions : kyc
    users ||--o{ kyb_reviews : reviews

    accounts ||--|| policies : policy
    accounts ||--o{ payments : payments
    accounts ||--o{ topups : topups
    accounts ||--o{ ledger_entries : ledger
    accounts ||--o{ transfers : from_or_to
    accounts ||--o{ approval_requests : approvals

    bank_links ||--o{ topups : funds

    webhook_endpoints ||--o{ webhook_deliveries : deliveries

    payments ||--o| approval_requests : approval
```
