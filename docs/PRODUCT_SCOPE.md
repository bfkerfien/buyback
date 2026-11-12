# Product scope

## Roles

- Customer: creates appraisals and submits items for buyback.
- Runner: accepts logistics jobs, moves contracts/items, records delivery.
- Administrator: configures service policy, pricing, access, and reconciliations.

## Initial modules

1. Identity and EVE SSO
2. Character and corporation linking
3. Item parsing and EVE SDE type resolution
4. Price sourcing and appraisal calculation
5. Buyback quote lifecycle
6. Runner job/contract workflow
8. Payout ledger and audit trail
9. Administration and public activity feed

LP Exchange is explicitly deferred.

## Core invariants

- A quote stores an immutable valuation snapshot: price source, timestamp, quantities, fees, and calculation version.
- A contract cannot be marked delivered solely from user input; ESI verification is required where ESI exposes the relevant data.
- Ledger entries are append-only and every balance-affecting action is traceable to an actor and source event.
- Payout transitions are idempotent and use database transactions/locking.
